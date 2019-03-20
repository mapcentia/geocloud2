<?php
/**
 * @author     Aleksandr Shumilov <shumsan1011@gmail.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models\interfaces;

interface UserInterface {
    public function getAll();
    public function getData();
    public function createUser(array $data);
    public function updateUser(array $data);
    public function deleteUser(string $data);
}
