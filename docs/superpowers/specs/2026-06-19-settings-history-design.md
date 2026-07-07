# Design: Historik på `settings.key_value` og `settings.geometry_columns_join`

> Dato: 2026-06-19
> Status: Godkendt design — klar til implementeringsplan

## Formål

`settings.key_value` og `settings.geometry_columns_join` indeholder systemopsætning
og konfiguration for hver GC2-database. Vi vil have fuld historik på begge tabeller,
så enhver ændring (oprettelse, opdatering, sletning) kan spores og tidligere tilstande
kan genskabes.

Historikken gemmes i **to separate tabeller** og fyldes via **database-triggers**, så
historikken er uafhængig af hvilken kodevej der skriver til tabellerne (PHP-model,
SQL-API, manuelle ændringer).

## Beslutninger (afklaret med bruger)

| Emne | Valg |
|------|------|
| Lagringsformat | **Spejlede kolonner** — historik-tabel har samme kolonner som kilden + audit-metadata (ikke fuld JSONB-snapshot) |
| Operationer | **INSERT, UPDATE og DELETE** logges alle |
| Bruger-sporing | **Kun DB-bruger** (`current_user`) + tidspunkt — ingen ændringer i app-koden |
| Trigger-arkitektur | **Tilgang A** — én generisk funktion delt af begge tabeller, dynamisk drevet |

## Arkitektur

### 1. Historik-tabeller

To tabeller i `settings`-skemaet:

- `settings.key_value_history`
- `settings.geometry_columns_join_history`

Hver oprettes som en spejling af kildetabellen:

```sql
CREATE TABLE settings.key_value_history (LIKE settings.key_value);
```

`LIKE` uden options kopierer **kolonnenavne, typer og NOT NULL**, men *ikke*
primærnøgle, defaults, sekvenser eller indeks. Det er præcis hvad vi vil have:
samme `id`/`_key_` skal kunne optræde i mange historik-rækker.

Derefter tilføjes audit-kolonner (genkørselssikkert med `IF NOT EXISTS`):

| Kolonne | Type | Beskrivelse |
|---------|------|-------------|
| `history_id` | `BIGSERIAL` | Surrogatnøgle og kronologisk rækkefølge. Egen sekvens pr. tabel. |
| `history_operation` | `CHAR(1)` | `'I'`, `'U'` eller `'D'`. |
| `history_db_user` | `TEXT` | `current_user` på skrivetidspunktet. |
| `history_timestamp` | `TIMESTAMPTZ` | `now()` (transaktionens starttid). |

> `history_id` (BIGSERIAL) giver en pålidelig kronologisk sortering selv hvis flere
> rækker deler samme `history_timestamp`.

### 2. Generisk trigger-funktion

Én funktion deles af begge tabeller:

```sql
CREATE OR REPLACE FUNCTION settings.history_trigger() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
    hist_table text := TG_TABLE_NAME || '_history';
    rec        record;
    seq        text;
    payload    jsonb;
BEGIN
    rec := CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
    seq := pg_get_serial_sequence('settings.' || hist_table, 'history_id');
    payload := to_jsonb(rec) || jsonb_build_object(
        'history_id',        nextval(seq),
        'history_operation', left(TG_OP, 1),
        'history_db_user',   current_user,
        'history_timestamp', now()
    );
    EXECUTE format(
        'INSERT INTO settings.%I SELECT (jsonb_populate_record(NULL::settings.%I, $1)).*',
        hist_table, hist_table
    ) USING payload;
    RETURN NULL;
END;
$$;
```

**Sådan tilpasser den sig nye kolonner automatisk:** `to_jsonb(rec)` indeholder altid
kildetabellens aktuelle kolonner; `jsonb_populate_record` mapper kun de nøgler der også
findes i historik-tabellen. Tilføjes en kolonne til kilden, tilføjer man blot samme
kolonne til historik-tabellen (én `ALTER`) — funktionen røres aldrig. Glemmer man det,
tabes blot den ene kolonnes værdi; logningen fortsætter uændret (graceful degradation).

**Typesikkerhed:** Begge måltabeller bruger kun simple typer (text, varchar, bool, int,
uuid, timestamptz, jsonb) som round-tripper korrekt gennem `to_jsonb` →
`jsonb_populate_record`. Ingen `geometry`- eller `hstore`-kolonner er involveret.

### 3. Triggers

```sql
DROP TRIGGER IF EXISTS key_value_history_tr ON settings.key_value;
CREATE TRIGGER key_value_history_tr
    AFTER INSERT OR UPDATE OR DELETE ON settings.key_value
    FOR EACH ROW EXECUTE FUNCTION settings.history_trigger();
```

Tilsvarende `geometry_columns_join_history_tr` på `settings.geometry_columns_join`.

`AFTER`-triggers (logger faktisk gennemførte ændringer), `FOR EACH ROW` (kræves for
adgang til `OLD`/`NEW`). `DROP … IF EXISTS` før `CREATE` gør oprettelsen genkørselssikker.

### 4. Semantik for hvilken række der gemmes

| Operation | Gemt række | Begrundelse |
|-----------|-----------|-------------|
| INSERT | `NEW` | Den oprettede tilstand. |
| UPDATE | `NEW` | Den resulterende tilstand efter ændringen. |
| DELETE | `OLD` | Den sidste tilstand før sletning (findes ikke længere live). |

Konsekvens: hver historik-række repræsenterer en faktisk skreven tilstand. Union af
historik-tabellen og den live tabel dækker alle tilstande tabellen har haft.

> Alternativ (ikke valgt): gem `OLD` ved UPDATE for at se "før-billedet". Sig til hvis
> dette foretrækkes — det er en ét-linjes ændring i `CASE`-udtrykket.

### 5. Placering og udrulning

Alle statements appendes til `Sql::get()` i `app/migration/Sql.php`. `run.php` kører hvert
statement i sin egen transaktion mod hver GC2-database og sluger fejl — derfor skal hvert
statement være genkørselssikkert:

1. `CREATE TABLE settings.<x>_history (LIKE settings.<x>)` — fejler hvis tabel findes (sluges).
2. `ALTER TABLE … ADD COLUMN IF NOT EXISTS …` for hver audit-kolonne.
3. `CREATE OR REPLACE FUNCTION settings.history_trigger()` — idempotent.
4. `DROP TRIGGER IF EXISTS …` + `CREATE TRIGGER …` for hver tabel.

Rækkefølge: historik-tabeller → audit-kolonner → funktion → triggers.

## Forespørgsel

```sql
-- Historik for en enkelt nøgle i key_value
SELECT history_id, history_operation, history_db_user, history_timestamp, value
FROM   settings.key_value_history
WHERE  key = 'someKey'
ORDER  BY history_id;

-- Historik for et lag i geometry_columns_join
SELECT history_id, history_operation, history_db_user, history_timestamp, *
FROM   settings.geometry_columns_join_history
WHERE  _key_ = 'schema.table.the_geom'
ORDER  BY history_id;
```

## Bevidste fravalg (YAGNI)

- **Ingen backfill** af eksisterende rækker som start-snapshot. Kan tilføjes senere som
  ét `INSERT … SELECT … , 'I', current_user, now()` pr. tabel hvis ønsket.
- **Ingen oprydning/retention** af historik. Tabellerne vokser ubegrænset; en eventuel
  pruning-strategi er en separat opgave.
- **Ingen app-bruger-sporing.** Kun `current_user` (DB-rollen) registreres.

## Verifikation

- Kør migrationen mod en testdatabase og bekræft at begge historik-tabeller, funktionen
  og begge triggers oprettes.
- INSERT/UPDATE/DELETE en række i hver kildetabel og bekræft tilsvarende historik-række
  med korrekt `history_operation` og indhold.
- Tilføj en testkolonne til en kildetabel uden at opdatere historik-tabellen og bekræft
  at logning fortsætter (graceful degradation).
- Kør migrationen to gange og bekræft at anden kørsel ikke fejler destruktivt.
