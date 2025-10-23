<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Model;
use Random\RandomException;


/**
 * Class Keyvalue
 * @package app\models
 */
class Client extends Model
{
    public function __construct(?Connection $connection = null)
    {
        parent::__construct($connection);
    }
    /**
     * @throws GC2Exception
     */
    public function get(?string $id = null): array
    {
        $sql = 'SELECT id,name,homepage,description,redirect_uri,public,confirm,two_factor,allow_signup,social_signup FROM settings.clients';
        $params = [];
        if ($id != null) {
            $sql .= ' WHERE id = :id';
            $params[':id'] = $id;
        }
        $res = $this->prepare($sql);
        $this->execute($res, $params);
        $data = $this->fetchAll($res, 'assoc');
        $data = array_map(function ($datum) {
            $datum['redirect_uri'] = json_decode($datum['redirect_uri']);
            return $datum;
        }, $data);
        if (sizeof($data) == 0) {
            throw new GC2Exception("No clients", 404, null, 'CLIENT_NOT_FOUND');
        }
        return $data;
    }

    /**
     * @throws RandomException
     */
    public function insert(string $id, string $name, string $redirectUri, ?string $homepage, ?string $description, bool $public = false, bool $confirm = true, bool $twoFactor = true, bool $allowSignup = false, bool $socialSignup = false): array
    {
        $sql = 'INSERT INTO settings.clients (id, secret, name, homepage, description, redirect_uri, "public", confirm, two_factor, allow_signup, social_signup) VALUES (:id, :secret, :name, :homepage, :description, :redirect_uri, :public, :confirm, :two_factor, :allow_signup, :social_signup)';
        $id = Model::toAscii($id);
        $secret = bin2hex(random_bytes(32));
        $secretHash = password_hash($secret, PASSWORD_BCRYPT);
        $homepage = $homepage ?? null;
        $description = $description ?? null;
        $res = $this->prepare($sql);
        $public = $public ? 't' : 'f';
        $confirm = $confirm ? 't' : 'f';
        $twoFactor = $twoFactor ? 't' : 'f';
        $allowSignup = $allowSignup ? 't' : 'f';
        $socialSignup = $socialSignup ? 't' : 'f';
        $this->execute($res, [
            'id' => $id,
            'secret' => $secretHash,
            'name' => $name,
            'homepage' => $homepage,
            'description' => $description,
            'redirect_uri' => $redirectUri,
            'public' => $public,
            'confirm' => $confirm,
            'two_factor' => $twoFactor,
            'allow_signup' => $allowSignup,
            'social_signup' => $socialSignup,
        ]);
        return ['id' => $id, 'secret' => $secret];
    }

    /**
     * @throws GC2Exception
     */
    public function update(string $id, ?string $newId, ?string $name, ?string $redirectUri, ?string $homepage, ?string $description, ?bool $public, ?bool $confirm, ?bool $twoFactor, ?bool $allowSignup, ?bool $socialSignup): string
    {
        $sets = [];
        $values = [];
        $values['id'] = $id;
        if ($newId) {
            $sets[] = "id=:newId";
            $values['newId'] = $newId;
        }
        if ($name) {
            $sets[] = "name=:name";
            $values['name'] = $name;
        }
        if ($redirectUri) {
            $sets[] = "redirect_uri=:redirect_uri";
            $values['redirect_uri'] = $redirectUri;
        }
        if ($homepage) {
            $sets[] = "homepage=:homepage";
            $values['homepage'] = $homepage;
        }
        if ($description) {
            $sets[] = "description=:description";
            $values['description'] = $description;
        }
        if (isset($public)) {
            $sets[] = "\"public\"=:public";;
            $values['public'] = $public ? 't' : 'f';
        }
        if (isset($confirm)) {
            $sets[] = "confirm=:confirm";;
            $values['confirm'] = $confirm ? 't' : 'f';
        }
        if (isset($twoFactor)) {
            $sets[] = "two_factor=:two_factor";;
            $values['two_factor'] = $twoFactor ? 't' : 'f';
        }
        if (isset($allowSignup)) {
            $sets[] = "allow_signup=:allow_signup";
            $values['allow_signup'] = $allowSignup ? 't' : 'f';
        }
        if (isset($socialSignup)) {
            $sets[] = "social_signup=:social_signup";
            $values['social_signup'] = $socialSignup ? 't' : 'f';
        }
        $setStr = implode(', ', $sets);
        $sql = "UPDATE settings.clients set $setStr  WHERE id = :id RETURNING id";
        $res = $this->prepare($sql);
        $this->execute($res, $values);
        if ($res->rowCount() == 0) {
            throw new GC2Exception("No client with id", 404, null, 'CLIENT_NOT_FOUND');
        }
        return $res->fetchColumn();
    }

    /**
     * @throws GC2Exception
     */
    public function delete($id): void
    {
        $sql = 'DELETE FROM settings.clients WHERE id = :id';
        $res = $this->prepare($sql);
        $this->execute($res, ['id' => $id]);
        if ($res->rowCount() == 0) {
            throw new GC2Exception("No client with id", 404, null, 'CLIENT_NOT_FOUND');
        }
    }

    /**
     * @throws GC2Exception
     */
    public function verifySecret(string $id, ?string $secret): void
    {
        if (empty($secret)) {
            throw new GC2Exception("Secret can not be empty", 401, null, 'SECRET_NOT_VERIFIED');
        }
        $sql = 'SELECT secret FROM settings.clients where id=:id';
        $res = $this->prepare($sql);
        $this->execute($res, ['id' => $id]);
        $hash = $this->fetchRow($res, 'assoc')['secret'];
        if (!password_verify($secret, $hash)) {
            throw new GC2Exception("Secret can not be verified", 401, null, 'SECRET_NOT_VERIFIED');
        }
    }
}
