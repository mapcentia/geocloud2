.. _styling:

#################################################################
Tematisering
#################################################################

.. topic:: Overview

    :Date: |today|
    :GC2-version: 2020.12.0
    :Forfatter: `mapcentia <https://github.com/mapcentia>`_, `giovanniborella <https://github.com/giovanniborella>`_

.. contents:: 
    :depth: 3


*****************************************************************
Tematisering
***************************************************************** 

.. include:: ../../_subs/NOTE_GETTINGSTARTED.rst

Alt data der udstilles igennem GC2 er opdelt i lag. Disse ligger i et skama, som igen ligger i en database. Databasen er oprettet når der oprettes en bruger, derefter kan der oprettes flere skemaer. Subusers kan også have deres eget skema, se mere her :ref:`subuser`.

Der findes flere forskellige lag:
* x 

.. _styling_wizard:

Class Wizard
=================================================================

.. include:: ../../_subs/WARNING_OLD_DOC.rst


For nemt at oprette en tematisering, er der lavet en wizard.

.. figure:: ../../../_media/styling-wizard.png
    :width: 400px
    :align: center
    :name: styling-wizard
    :figclass: align-center

    ``Class wizard`` i ``Map``-fanen


Start med at vælge det lag du gerne vil tematisere. Lagene er samlet i de grupper som er defineret i deres :ref:`layer_properties`.

1. Vælg relevant lag der skal tematiseres.

2. Klik på ``Class Wizard``

Tematiseringen af styres igennem regler kaldet ``class``. Dette svarer til et reglset i MapServer-komponenter, og mange af de samme tematiseringsmuligheder er i GC2.

.. _styling_wizard:

Wizard-typer
-----------------------------------------------------------------

.. include:: ../../_subs/WARNING_OLD_DOC.rst

.. figure:: ../../../_media/styling-wizard-open.png
    :width: 400px
    :align: center
    :name: styling-wizard-open
    :figclass: align-center

    ``Class wizard`` viser altid den sidst brugte opsætning

Man bliver præsenteret for 4 faner med hver sin tematisering. Felterne i (**6**) er tilgængelige for alle 4 faner, hvorimod de obligatoriske felter i (**5**) er forskellige for hver fane.

1.  ``Enkelt`` er den mest simple tematisering. Denne stilart bliver sat når der bliver uploaded ny data. Der bliver lavet en ``class`` med den valgte farve.
    * ``Farve`` - den faste farve som features får.

2. ``Unique`` laver en ``class`` for hver unik værdi i det valgte felt.
    * ``Felt`` - vælg hvilken kolonne værdierne skal læses fra.
    * ``Farve`` - Det er muligt at vælge tilfældige farver, eller en af de forud-defineret farve-paletter.

.. note::
    Bemærk at denne stilart ikke sikrer at nye unikke værdier får deres egen tematisering. Dette er en enten-eller regl. Hvis der kommer features som ikke er defineret som en ``expression``, vil de ikke blive udtegnet.

3. ``Intervaller`` laver en ``class`` for et interval. Denne funktion virker kun på nummeriske kolonner.
    * ``Type`` - Det er muligt at vælge mellem  ``Equal`` el. ``Quantile`` til beregningsmetoden for intervallerne.
    * ``Nummerisk felt`` - Hvilken kolonne skal bruges til beregningen.
    * ``Antal af farver`` - Vælg antal af intervaller der skal beregnes.
    * ``Start Farve`` - Vælg den indledende farve
    * ``Slut Farve`` - Vælg den afsluttende farve

4. ``Clusters`` laver en gruppering af punkter eller features. Det bliver lavet en ``class`` for grupperingen, og den enkelte feature.
    * ``Afstand`` - heltal i ``pixels`` som angiver hvor tæt features skal ligge på hinanden for at indgå i en cluster.

.. _styling_wizard_symbol:

Symbol
-----------------------------------------------------------------

.. include:: ../../_subs/WARNING_OLD_DOC.rst

.. figure:: ../../../_media/styling-wizard-symbol.png
    :width: 400px
    :align: center
    :name: styling-wizard-symbol
    :figclass: align-center

    Indstillinger for ``Symbol``

1. ``Symbol`` - Angiv hvilket symbol der skal bruges. Hvis feltet er tomt bliver flader og linjer udtegnet som normalt. 

2. ``Angle`` - Symbolets rotation i ``°`` mod uret. [#combo]_

3. ``Size`` - Symbolets højde i ``pixels``. [#combo]_

4. ``Outline farve`` - Farve der bruges i kanten af flader, og på bestemte symboler. Har ingen effekt på linje-lag.

5. ``Line width`` - Tykkelse på linjer i ``pixels``

6. ``Gennemsigtighed`` - Angiv gennemsigtighed på objektet på en skala fra ``1 - 100`` hvor ``100`` er solid. 

.. _styling_wizard_label:

Label
-----------------------------------------------------------------

.. include:: ../../_subs/WARNING_OLD_DOC.rst

.. figure:: ../../../_media/styling-wizard-label.png
    :width: 400px
    :align: center
    :name: styling-wizard-label
    :figclass: align-center

    Indstillinger for ``Label``

1. ``Text`` - Tekst der skal vises som label. [#combo]_

.. note::
    Det er også muligt at angive flere kolonner, eller sammensatte tekster. 

    ``No. [id] \n [text]`` vil samle teksterne fra ``id`` og ``text``.


2. ``Color`` - Farce for label. [#combo]_

3. ``Size`` - Størrelse på label i ``pixels``. [#combo]_

4. ``Position`` - Position af label relativt til ankerpunkt

5. ``Angle`` - Labels rotation i ``°`` mod uret. [#combo]_

6. ``Background`` - Baggrundsfarve for label. Dette vil danne en polygon bag teksten som kan gøre den mere læsbar.

7. ``Font`` - Skrifttype for label

8. ``Font weight`` - Vægt af skrifttype. ``Normal``, ``Bold``, ``Italic``, ``Bold italic``

.. _styling_manual:

Manuel tematisering
=================================================================

.. include:: ../../_subs/WARNING_OLD_DOC.rst

.. figure:: ../../../_media/styling-manual.png
    :width: 400px
    :align: center
    :name: styling-manual
    :figclass: align-center

    Manuel adgang til ``class``

Efter man indledningsvis har brugt ``Class Wizard`` til at oprette den ønskede tematisering, er det muligt at lave en yderligere tilpasning.

1. Åbn panelet ved at klikke på det valgte lag.

.. _styling_manual_classes:

Klasser
-----------------------------------------------------------------

.. include:: ../../_subs/WARNING_OLD_DOC.rst

.. figure:: ../../../_media/styling-manual-classes.png
    :width: 200px
    :align: center
    :name: styling-manual-classes
    :figclass: align-center

    ``class``-panel

1. Det øverste grid vider de eksiterende klasser. Der skal være mindst 1 klasse for at laget kan blive vist på kortet.Hvis en klasse bliver valgt vil værdierne blive vist i panelet under.

2. Egenskaber opdelt i fanerne: ``Base``, ``Symbol1``, ``Symbol2``, ``Label1``, ``Label2``

.. _styling_manual_base:

Base
-----------------------------------------------------------------

.. include:: ../../_subs/WARNING_OLD_DOC.rst

.. figure:: ../../../_media/styling-manual-base.png
    :width: 200px
    :align: center
    :name: styling-manual-base
    :figclass: align-center

    ``class``-panel

I denne fase er de grundlæggende egenskaber.

1. ``Name`` - Klassens navn. Denne bliver vist i signaturforklaringen.

2. ``Expression`` - Hver feature i laget bliver test imod dette udtryk. Kun hvis der returneres ``true`` til udtrykket bliver det tegnet ud. Hvis expression er tom, vil alle features udtegnes med denne klasse. `Læs mere om expressions her <https://mapserver.org/mapfile/expressions.html>`_

3. ``Min scale denominator`` - bestemmer ved hvilken minimums-skala denne class skal udtegnes. Bliver angivet som ``24000`` for ``1:24000``.

4. ``Max scale denominator`` - bestemmer ved hvilken maksimum-skala denne class skal udtegnes. Bliver angivet som ``24000`` for ``1:24000``.

5. ``Sort id`` - Angiver hvilken rækkefølge denne class har i signaturforklaringen. Denne indstilling ændrer ikke for rækkefølgen af features der bliver udskrevet i kortet.

.. _styling_manual_symbol:

Symbol1/Symbol2
-----------------------------------------------------------------

.. include:: ../../_subs/WARNING_OLD_DOC.rst

.. figure:: ../../../_media/styling-manual-symbol.png
    :width: 200px
    :align: center
    :name: styling-manual-symbol
    :figclass: align-center

    ``Symbol*``-panel

Det er muligt at angive 2 symboler i samme ``class``. ``Symbol1`` vil være placeret under ``Symbol2``. Dette gør det muligt at lave en mere komplekt symbologi.

1. ``Symbol size``, ``Symbol angle`` [#combo]_

.. _styling_manual_label:

Label1/Label2
-----------------------------------------------------------------

.. include:: ../../_subs/WARNING_OLD_DOC.rst

.. figure:: ../../../_media/styling-manual-label.png
    :width: 200px
    :align: center
    :name: styling-manual-label
    :figclass: align-center

    ``Symbol*``-panel

Det er muligt at angive 2 labels i samme ``class``. Dette gør det muligt at lav ew

1. ``On`` - Sæt denne til ``true`` for at vise labels.

2. ``Text`` - Tekst der skal vises som label. [#combo]_

.. note::
    Det er også muligt at angive flere kolonner, eller sammensatte tekster. 

    ``No. [id] \n [text]`` vil samle teksterne fra ``id`` og ``text``.

3. ``Force`` - Gennemtvig udskrivning af label, på trods af kollisioner.

4. ``Min scale denominator`` - bestemmer ved hvilken minimums-skala denne class skal udtegnes. Bliver angivet som ``24000`` for ``1:24000``.

5. ``Max scale denominator`` - bestemmer ved hvilken maksimum-skala denne class skal udtegnes. Bliver angivet som ``24000`` for ``1:24000``.

6. ``Position`` - Position af label relativt til ankerpunkt

7. ``Size`` - Størrelse på label i ``pixels``. [#combo]_


.. _styling_qgis:

QGIS
=================================================================

Det er også muligt at tematisere alle sine lag på én gang, eller enkeltvis med at uploade et QGIS-projekt som beskrevet her: :ref:`layer_create_qgis`. Tematiseringen vil i vid udstrækning blive overført fra projektet.

.. note::
    Hvis lagene bliver tematiseret gennem QGIS-projekt skal man være opmærksom på at hele projektet skal læses af MapServer inden der kan returneres et svar til klienten. Det betyder at man kan hente et væsentligt performance-boost ved at tematisere sine lag igennem MapServer.

    Hvis man angiver sin tematisering igennem QGIS første gang, vil ændringer i :ref:`styling_manual` overskrive projektet.


.. rubric:: Footnotes

.. [#combo] Combobox - Man kan angive en fast værdi, eller koble til en kolonne.