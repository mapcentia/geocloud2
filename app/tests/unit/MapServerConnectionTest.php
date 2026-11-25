<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\conf\Connection as Conf;
use app\inc\Connection;
use app\inc\MapServerConnection;
use Codeception\Test\Unit;

/**
 * MapServer's own database connection: every setting falls back, one by one,
 * to GC2's normal connection.
 */
class MapServerConnectionTest extends Unit
{
    protected UnitTester $tester;
    private const array ENV = ['MAPSERVER_POSTGRES_HOST', 'MAPSERVER_POSTGRES_PORT', 'MAPSERVER_POSTGRES_USER',
        'MAPSERVER_POSTGRES_PASSWORD', 'MAPSERVER_POSTGRES_PGBOUNCER'];
    /** @var array<string, mixed> */
    private array $paramBefore;
    /** @var array<string, string|false> */
    private array $envBefore = [];

    protected function _before(): void
    {
        include_once '/var/www/geocloud2/app/conf/App.php';
        include_once '/var/www/geocloud2/app/conf/Connection.php';
        $this->paramBefore = Conf::$param;
        foreach (self::ENV as $name) {
            $this->envBefore[$name] = getenv($name);
            putenv($name);
        }
        unset(Conf::$param['mapserver'], Conf::$param['mapserverhost']);
    }

    protected function _after(): void
    {
        Conf::$param = $this->paramBefore;
        foreach ($this->envBefore as $name => $value) {
            $value === false ? putenv($name) : putenv("$name=$value");
        }
    }

    private function normal(bool $pgbouncer = false): Connection
    {
        return new Connection(host: 'postgres', user: 'gc2', password: 'normal-pw', database: 'mydb', port: '5432',
            pgbouncer: $pgbouncer);
    }

    public function testWithNothingConfiguredItIsTheNormalConnection(): void
    {
        $c = $this->normal();
        $m = MapServerConnection::resolve($c);
        $this->assertSame($c->host, $m->host);
        $this->assertSame($c->port, $m->port);
        $this->assertSame($c->user, $m->user);
        $this->assertSame($c->password, $m->password);
        $this->assertSame('mydb', $m->database);
        $this->assertFalse($m->pgbouncer);
        $this->assertTrue(MapServerConnection::resolve($this->normal(pgbouncer: true))->pgbouncer,
            'the normal connection being a PgBouncer carries over');
    }

    public function testEachSettingFallsBackOnItsOwn(): void
    {
        Conf::$param['mapserver'] = ['host' => 'pgbouncer', 'port' => '6432', 'pgbouncer' => true];
        $c = $this->normal();
        $m = MapServerConnection::resolve($c);
        $this->assertSame('pgbouncer', $m->host);
        $this->assertSame('6432', $m->port);
        $this->assertTrue($m->pgbouncer);
        $this->assertSame($c->user, $m->user, 'user was not configured');
        $this->assertSame($c->password, $m->password, 'password was not configured');
        $this->assertSame('mydb', $m->database, 'the database is always the request\'s own');
    }

    public function testAReadReplicaWithItsOwnCredentials(): void
    {
        Conf::$param['mapserver'] = ['host' => 'replica', 'user' => 'reader', 'password' => 'replica-pw'];
        $m = MapServerConnection::resolve($this->normal());
        $this->assertSame('replica', $m->host);
        $this->assertSame('5432', $m->port);
        $this->assertSame('reader', $m->user);
        $this->assertSame('replica-pw', $m->password);
        $this->assertFalse($m->pgbouncer);
    }

    public function testTheEnvironmentIsReadAndTheFileWins(): void
    {
        putenv('MAPSERVER_POSTGRES_HOST=from-env');
        putenv('MAPSERVER_POSTGRES_PORT=6432');
        putenv('MAPSERVER_POSTGRES_PGBOUNCER=true');
        $m = MapServerConnection::resolve($this->normal());
        $this->assertSame('from-env', $m->host);
        $this->assertSame('6432', $m->port);
        $this->assertTrue($m->pgbouncer);

        Conf::$param['mapserver'] = ['host' => 'from-file', 'pgbouncer' => false];
        $m = MapServerConnection::resolve($this->normal());
        $this->assertSame('from-file', $m->host);
        $this->assertSame('6432', $m->port, 'not in the file, so still the environment');
        $this->assertFalse($m->pgbouncer, 'an explicit false in the file is a setting, not a gap');
    }

    public function testEmptyValuesAreGaps(): void
    {
        putenv('MAPSERVER_POSTGRES_HOST=');
        Conf::$param['mapserver'] = ['port' => '', 'user' => null];
        $c = $this->normal();
        $m = MapServerConnection::resolve($c);
        $this->assertSame($c->host, $m->host);
        $this->assertSame($c->port, $m->port);
        $this->assertSame($c->user, $m->user);
    }

    public function testTheOldHostOnlySettingStillWorks(): void
    {
        Conf::$param['mapserverhost'] = 'old-style-host';
        $this->assertSame('old-style-host', MapServerConnection::resolve($this->normal())->host);
        Conf::$param['mapserver'] = ['host' => 'new-style-host'];
        $this->assertSame('new-style-host', MapServerConnection::resolve($this->normal())->host);
    }

    /**
     * A Mapfile bound to $c, without running its constructor: that reads the
     * database's settings and needs the cache, and switching the cache on here
     * would leave it on for every later test in the suite.
     */
    private function mapfile(Connection $c): \app\models\Mapfile
    {
        $class = new ReflectionClass(\app\models\Mapfile::class);
        $mapfile = $class->newInstanceWithoutConstructor();
        $class->getProperty('mapServer')->setValue($mapfile, MapServerConnection::resolve($c));
        return $mapfile;
    }

    /** A PgBouncer rejects startup options, so the connection string must not carry them. */
    public function testTheMapfileConnectionStringFollowsIt(): void
    {
        $c = $this->normal();
        $direct = $this->mapfile($c)->renderPostgisConnection();
        // The user is the install's own unless App.php sets "setUser", so read it back
        $this->assertSame("user={$c->user} dbname=mydb host=postgres port=5432 password=normal-pw options='-c client_encoding=UTF8'", $direct);

        Conf::$param['mapserver'] = ['host' => 'pgbouncer', 'port' => '6432', 'pgbouncer' => true];
        $pooled = $this->mapfile($c)->renderPostgisConnection();
        $this->assertSame("user={$c->user} dbname=mydb host=pgbouncer port=6432 password=normal-pw", $pooled);
    }
}
