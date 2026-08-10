<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;


use app\exceptions\ServiceException;
use app\models\Setting;
use PDOException;
use Psr\Cache\InvalidArgumentException;

final class BasicAuth
{
    private string $user;
    private bool $isSession;
    private bool $isSubuser = false;

    function __construct(public ?Connection $connection = null)
    {
        if ($this->connection == null) {
            $this->connection = new Connection();
        }
        $this->isSession = Session::isAuth();
        if ($this->isSession) {
            $this->user = Session::getUser();
            $this->isSubuser = Session::isSubUser();
        }
    }

    /**
     * Authenticates a user based on their credentials and sets session information if successful.
     * The method also evaluates user privileges for accessing certain layers.
     *
     * @param string $layerName The name of the layer to be accessed. It is used to determine the schema and check user privileges.
     * @param bool $isTransaction
     * @return void
     * @throws ServiceException|InvalidArgumentException
     * @throws \Throwable
     */
    public function authenticate(string $layerName, bool $isTransaction): void
    {
        $setting = new Setting(connection: $this->connection);
        $settings = $setting->get();
        if (!$this->isSession || $_SESSION['parentdb'] != $setting->postgisdb) {
            if (!empty(Input::getAuthUser())) {
                $this->user = Input::getAuthUser();
                $password = Input::getAuthPw();
            }
            if (!empty($this->user) && isset($password)) {
                $this->isSubuser = $this->user != $setting->postgisdb;
                $passwordCheck = !$this->isSubuser ? $settings["data"]->pw : $settings["data"]->pw_subuser->{$this->user};
            }
            if (empty($this->user) || empty($password) || empty($passwordCheck) || Setting::encryptPw($password) !== $passwordCheck) {
                self::setAuthHeader($setting->postgisdb);
            }
        }
        $userGroup = !empty($settings["data"]->userGroups->{$this->user}) ? $settings["data"]->userGroups->{$this->user} : null;

        // AUTHENTICATION SUCCESSFUL
        $split = explode(".", $layerName);
        $schema = $split[0];
        if ($this->isSubuser && $this->user != $schema) {
            $schema = $split[0];
            $table = $split[1];
            $sql = "SELECT * FROM settings.getColumns('f_table_schema = ''$schema'' AND f_table_name = ''$table''', 'r_table_schema = ''$schema'' AND r_table_name = ''$table''')";
            $postgisObject = new Model(connection: $this->connection);
            $res = $postgisObject->prepare($sql);
            try {
                $postgisObject->execute($res);
            } catch (PDOException $e) {
                throw new ServiceException($e->getMessage());
            }
            while ($row = $postgisObject->fetchRow($res)) {
                $privileges = json_decode($row["privileges"]);
                $prop = $userGroup ?: $this->user;
                if ((!$privileges->$prop || $privileges->$prop == "none" || ($privileges->$prop == "read" && $isTransaction)) && ($prop != $schema)) {
                    throw new ServiceException("You don't have privileges to this layer. Please contact the database owner, which can grant you privileges.");
                }
            }
        }
    }

    /**
     * Verifies the presented HTTP Basic credentials against the stored viewer
     * password WITHOUT any per-layer privilege check, and challenges with a 401
     * when credentials are present but wrong. No-op for anonymous requests (no
     * Authorization header) or when a matching session already exists.
     *
     * Use this to establish a trustworthy identity before per-layer auth fires:
     * requests that carry no layer (e.g. WFS/WMS GetCapabilities) never reach
     * authenticate(), so without this a fabricated Authorization header would be
     * trusted as the request identity.
     *
     * @throws InvalidArgumentException
     */
    public function verifyCredentials(): void
    {
        if ($this->isSession && ($_SESSION['parentdb'] ?? null) == $this->connection->database) {
            return; // already authenticated via session
        }
        $user = Input::getAuthUser();
        if (empty($user)) {
            return; // anonymous — no identity claimed, nothing to verify
        }
        $setting = new Setting(connection: $this->connection);
        $settings = $setting->get();
        $password = Input::getAuthPw();
        $isSubuser = $user != $setting->postgisdb;
        $passwordCheck = $isSubuser
            ? ($settings["data"]->pw_subuser->{$user} ?? null)
            : ($settings["data"]->pw ?? null);
        if (empty($password) || empty($passwordCheck) || Setting::encryptPw($password) !== $passwordCheck) {
            self::setAuthHeader($setting->postgisdb);
        }
    }

    /**
     * Sets the HTTP authentication headers for Basic Authentication
     * and terminates the script with an unauthorized response.
     *
     * @param string $realm The authentication realm to display in the WWW-Authenticate header.
     * @return never This method does not return a value as it terminates script execution.
     */
    private static function setAuthHeader(string $realm): never
    {
        header("WWW-Authenticate: Basic realm=\"$realm\"");
        header('HTTP/1.0 401 Unauthorized');
        header("Cache-Control: no-cache, must-revalidate");
        // Date in the past
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        // Text to send if user hits Cancel button
        die("Attempt to login using Basic Auth was cancelled");
    }
}
