<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\exceptions\GC2Exception;
use app\inc\Model;
use Random\RandomException;


/**
 * Class Keyvalue
 * @package app\models
 */
class Client extends Model
{
    /**
     * @throws GC2Exception
     */
    public function get(?string $id = null): array
    {
        $sql = 'SELECT id,name,homepage,description,redirect_uri FROM settings.clients';
        $params = [];
        if ($id != null) {
            $sql .= ' WHERE id = :id';
            $params[':id'] = $id;
        }
        $res = $this->prepare($sql);
        $res->execute($params);
        $data = $this->fetchAll($res, 'assoc');
        if (sizeof($data) == 0) {
            throw new GC2Exception("No clients", 404, null, 'CLIENT_NOT_FOUND');
        }
        return $data;
    }

    /**
     * @throws RandomException
     */
    public function insert(string $name, string $redirectUri, string $username, ?string $homepage, ?string $description): array
    {
        $sql = 'INSERT INTO settings.clients (id, secret, name, homepage, description, redirect_uri, username) VALUES (:id, :secret, :name, :homepage, :description, :redirect_uri, :username)';
        $id = uniqid();
        $secret = bin2hex(random_bytes(32));
        $secretHash = password_hash($secret, PASSWORD_BCRYPT);
        $homepage = $homepage ?? null;
        $description = $description ?? null;
        $res = $this->prepare($sql);
        $res->execute([
            'id' => $id,
            'secret' => $secretHash,
            'name' => $name,
            'homepage' => $homepage,
            'description' => $description,
            'redirect_uri' => $redirectUri,
            'username' => $username,
        ]);
        return ['id' => $id, 'secret' => $secret];
    }

    /**
     * @throws GC2Exception
     */
    public function update(string $id, ?string $name, ?string $redirectUri, ?string $homepage, ?string $description): void
    {
        $sets = [];
        $values = [];
        $values['id'] = $id;
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
        $setStr = implode(', ', $sets);
        $sql = "UPDATE settings.clients set $setStr  WHERE id = :id";
        $res = $this->prepare($sql);
        $res->execute($values);
        if ($res->rowCount() == 0) {
            throw new GC2Exception("No client with id", 404, null, 'CLIENT_NOT_FOUND');
        }
    }

    /**
     * @throws GC2Exception
     */
    public function delete($id): void
    {
        $sql = 'DELETE FROM settings.clients WHERE id = :id';
        $res = $this->prepare($sql);
        $res->execute(['id' => $id]);
        if ($res->rowCount() == 0) {
            throw new GC2Exception("No client with id", 404, null, 'CLIENT_NOT_FOUND');
        }
    }

    /**
     * @throws GC2Exception
     */
    public function verifySecret(string $id, string $secret): void
    {
        $sql = 'SELECT secret FROM settings.clients where id=:id';
        $res = $this->prepare($sql);
        $res->execute(['id' => $id]);
        $hash = $this->fetchRow($res, 'assoc')['secret'];
        if (!password_verify($secret, $hash)) {
            throw new GC2Exception("Secret can not be verified", 404, null, 'SECRET_NOT_VERIFIED');
        }
    }
}
