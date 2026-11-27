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
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\MapSymbols;
use app\inc\Route2;
use OpenApi\Attributes as OA;
use Override;

/**
 * The built-in MapServer symbols a style's `symbol` can name, with the
 * geometry needed to draw a preview of each. Server constants, not data:
 * the same definitions (app\inc\MapSymbols) every generated mapfile contains,
 * so the list cannot drift from what MapServer is given.
 *
 * Read only and the same for every database, so no database access.
 *
 * @package app\api\v4
 */
#[OA\Schema(
    schema: "Symbol",
    description: "A built-in MapServer symbol. `name` is the value to put in a style's `symbol`.",
    required: ["name", "type", "filled", "points"],
    properties: [
        new OA\Property(property: "name", description: "Symbol name: exactly the value a style's `symbol` takes.", type: "string", example: "arrow"),
        new OA\Property(property: "type", description: "MapServer SYMBOL TYPE. `vector`: `points` is an outline in the symbol's own coordinate space (y grows downwards). `ellipse`: the first point is the x and y radius, so `[[1, 1]]` is a circle.", type: "string", enum: ["vector", "ellipse"], example: "vector"),
        new OA\Property(property: "filled", description: "Whether the shape is filled (MapServer FILLED) or only stroked.", type: "boolean", example: true),
        new OA\Property(property: "points", description: "The symbol's POINTS as [x, y] pairs, in mapfile order.", type: "array", items: new OA\Items(type: "array", items: new OA\Items(type: "number"), maxItems: 2, minItems: 2), example: [[0, 0.4], [3, 0.4], [3, 0], [5, 0.8], [3, 1.6], [3, 1.2], [0, 1.2], [0, 0.4]]),
        new OA\Property(property: "anchorpoint", description: "MapServer ANCHORPOINT as [x, y] fractions of the symbol's extent. Only present on symbols that define one.", type: "array", items: new OA\Items(type: "number"), maxItems: 2, minItems: 2, example: [0, 0.5]),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'OPTIONS'])]
#[Controller(route: 'api/v4/symbols/[name]', scope: Scope::SUB_USER_ALLOWED)]
class Symbol extends AbstractApi
{
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'symbols';
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/symbols/{name}', operationId: 'getSymbol', description: "Get the built-in MapServer symbols a style's `symbol` can name, with type and points so a client can draw a preview. Omit the name to list all, in the order the mapfile defines them. Several line symbols (dashed1, continue, dash-dot-dot, dot-dot) are the same ellipse as circle: their dash look comes from the style's `pattern` and `gap`. A style's `symbol` is not limited to this list; it may also be an image URL or a `[column]` reference.", summary: 'Get built-in symbols', security: [['bearerAuth' => []]], tags: ['Symbols'])]
    #[OA\Parameter(name: 'name', description: 'Symbol name, or a comma separated list of names. Omit to list all.', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: 'arrow')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Symbol"), new OA\Schema(type: 'array', items: new OA\Items(ref: "#/components/schemas/Symbol"))]))]
    #[OA\Response(response: 400, description: 'Missing or invalid token, or a malformed name (INVALID_REQUEST)')]
    #[OA\Response(response: 404, description: 'A named symbol does not exist (SYMBOL_NOT_FOUND)')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $all = MapSymbols::all();
        $name = $this->route->getParam("name");
        if ($name === null || $name === '') {
            return $this->getResponse($all);
        }
        $byName = array_column($all, null, 'name');
        $rows = [];
        // Every name is checked before anything is returned: an unknown one fails the whole request
        foreach (explode(',', $name) as $n) {
            if (!isset($byName[$n])) {
                throw new GC2Exception("Symbol not found: $n", 404, null, 'SYMBOL_NOT_FOUND');
            }
            $rows[] = $byName[$n];
        }
        return $this->getResponse($rows, single: count($rows) === 1);
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

    /**
     * @throws GC2Exception
     */
    #[Override]
    public function validate(): void
    {
        $name = $this->route->getParam("name");
        if ($name !== null && $name !== '' && !preg_match('/^[A-Za-z0-9_\-]+(,[A-Za-z0-9_\-]+)*$/', $name)) {
            throw new GC2Exception("Invalid symbol name. Use a name or a comma separated list of names", 400, null, 'INVALID_REQUEST');
        }
    }
}
