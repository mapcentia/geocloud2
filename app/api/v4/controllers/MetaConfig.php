<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\inc\Connection;
use app\inc\Globals;
use app\inc\Route2;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Override;

/**
 * The effective meta config: the form definition a client uses to edit the
 * free-form relation properties (the `properties` object of GET/PATCH
 * /api/v4/meta). It is server configuration, not data: the built-in fieldsets
 * (app\inc\Globals::$metaConfig) merged with the custom ones from App.php
 * `metaConfig`, de-duplicated by fieldsetName so a custom fieldset replaces the
 * built-in one of the same name.
 *
 * This is the same merged result the old GUI gets through /api/v1/baselayerjs,
 * so a client does not have to hardcode the relation properties form.
 *
 * Its own route rather than /api/v4/meta/config, because the {query} segment of
 * the Meta controller takes a relation or schema name and a schema may be
 * called "config".
 *
 * @package app\api\v4
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "MetaConfigFieldValue",
    description: "One choice of a `combo` or `checkboxgroup` field.",
    required: ["name", "value"],
    properties: [
        new OA\Property(property: "name", description: "Label shown to the user.", type: "string", example: "Vector"),
        new OA\Property(property: "value", description: "Value stored in the relation properties.", type: "string", example: "v"),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "MetaConfigField",
    description: "One field of a fieldset. `name` is the key it is stored under in the relation `properties` object.",
    required: ["name", "type", "title"],
    properties: [
        new OA\Property(property: "name", description: "Property key in the relation `properties` object.", type: "string", example: "vidi_layer_type"),
        new OA\Property(property: "type", description: "Form control to render. A `checkboxgroup` stores its selection as one comma separated string.", type: "string", enum: ["text", "textarea", "checkbox", "combo", "checkboxgroup"], example: "checkboxgroup"),
        new OA\Property(property: "title", description: "Label shown to the user.", type: "string", example: "Type"),
        new OA\Property(property: "values", description: "Choices of a `combo` or `checkboxgroup` field.", type: "array", items: new OA\Items(ref: "#/components/schemas/MetaConfigFieldValue")),
        new OA\Property(property: "default", description: "Value to use when the relation has no stored value for this field. A boolean for `checkbox`, a string otherwise.", oneOf: [new OA\Schema(type: "string"), new OA\Schema(type: "boolean")], example: "t"),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "MetaConfigFieldset",
    description: "A named group of fields, rendered as one fieldset in the relation properties form.",
    required: ["fieldsetName", "fields"],
    properties: [
        new OA\Property(property: "fieldsetName", description: "Name of the group. Unique across the returned list.", type: "string", example: "Layer type"),
        new OA\Property(property: "fields", type: "array", items: new OA\Items(ref: "#/components/schemas/MetaConfigField")),
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/meta-config', scope: Scope::SUB_USER_ALLOWED)]
class MetaConfig extends AbstractApi
{
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'meta-config';
    }

    #[OA\Get(path: '/api/v4/meta-config', operationId: 'getMetaConfig', description: "Get the effective meta config: the fieldsets and fields a client renders to edit the free-form relation `properties` of the Metadata API. The built-in fieldsets merged with the custom ones configured on the server, de-duplicated by fieldsetName (custom wins).", summary: 'Get the effective meta config', security: [['bearerAuth' => []]], tags: ['Metadata'],
        responses: [
            new OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: "#/components/schemas/MetaConfigFieldset"))),
            new OA\Response(response: 400, description: 'Missing or invalid token'),
        ],
    )]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        return $this->getResponse(Globals::getMetaConfig());
    }

    #[Override]
    public function post_index(): Response
    {
        // Read only
    }

    #[Override]
    public function put_index(): Response
    {
        // Read only
    }

    #[Override]
    public function patch_index(): Response
    {
        // Read only
    }

    #[Override]
    public function delete_index(): Response
    {
        // Read only
    }

    #[Override]
    public function validate(): void
    {
    }
}
