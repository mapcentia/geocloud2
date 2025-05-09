<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 *
 */

namespace app\extensions\demo\api;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\ApiInterface;
use app\exceptions\GC2Exception;
use app\inc\Input;
use app\inc\Route2;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

#[AcceptableMethods(['GET', 'POST', 'HEAD', 'OPTIONS'])]
class DemoRest implements ApiInterface
{
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function get_index(): array
    {
        return ['hello' => 'world'];
    }

    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function post_index(): array
    {
        $body = Input::getBody();
        return json_decode($body, true);
    }

    public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }

    public function patch_index(): array
    {
        // TODO: Implement patch_index() method.
    }

    public function delete_index(): array
    {
        // TODO: Implement delete_index() method.
    }

    // Custom action
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function get_my_resource(): array
    {
        $name = Route2::getParam("name");
        $message = Route2::getParam("message") ?? null;
        return ['hello' => $name, 'message' => $message];
    }

    /**
     * @throws GC2Exception
     */
    public function validate(): void
    {
        // TODO: Implement validate() method.
        if (false) {
            throw new GC2Exception('Something went wrong', 400, null, 'AN_ERROR_CODE');
        }

        // Use Symfony Validator to check the input
        if (Input::getMethod() == 'post') {
            $validator = Validation::createValidator();
            $collection = new Assert\Collection([
                'foo' => new Assert\Required([
                    new Assert\Type('string'),
                    new Assert\NotBlank(),
                ]),
                'bar' => new Assert\Optional([
                    new Assert\Type('string'),
                    new Assert\NotBlank(),
                    new Assert\Choice(['a', 'b', 'c']),
                ]),
            ]);
            $violations = $validator->validate(json_decode(Input::getBody(), true), $collection);
            AbstractApi::checkViolations($violations); // Throws exception if there are violations
        }
    }
}