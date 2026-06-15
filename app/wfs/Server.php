<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * Dispatches a parsed WFS Request to the right handler.
 * Throws OwsException / ServiceException for protocol-level errors;
 * caller (legacy adapter or v4 controller) is responsible for rendering
 * the exception report.
 */
namespace app\wfs;

use app\exceptions\OwsException;
use app\exceptions\ServiceException;
use app\inc\BasicAuth;
use app\inc\Input;
use app\wfs\output\GmlWriter;
use Psr\Cache\InvalidArgumentException;
use Throwable;

final class Server
{
    private const array HANDLERS = [
        'GETCAPABILITIES'     => handlers\GetCapabilities::class,
        'DESCRIBEFEATURETYPE' => handlers\DescribeFeatureType::class,
        'GETFEATURE'          => handlers\GetFeature::class,
        'TRANSACTION'         => handlers\Transaction::class,
    ];

    public function __construct(private readonly Context $ctx) {}

    /**
     * @throws OwsException
     */
    public function dispatch(Request $req, GmlWriter $writer): void
    {
        $this->validateProtocol($req);
        if ($req->operation !== 'GETCAPABILITIES') {
            $this->checkLayerEnabled($req);
            try {
                $this->basicAuthPerLayer($req);
            } catch (InvalidArgumentException) {

            } catch (ServiceException) {

            } catch (Throwable) {

            }
        }

        $class = self::HANDLERS[$req->operation]
            ?? throw new OwsException(
                "No such operation WFS $req->operation",
                attributes: ['exceptionCode' => 'OperationNotSupported', 'locator' => $req->operation]
            );

        new $class($this->ctx)->handle($req, $writer);
    }

    /**
     * @throws OwsException
     */
    private function validateProtocol(Request $req): void
    {
        if ($req->version !== '1.0.0' && $req->version !== '1.1.0') {
            throw new OwsException("Version $req->version is not supported");
        }
        if (strcasecmp($req->service, 'wfs') !== 0) {
            throw new OwsException(
                'No service',
                attributes: ['exceptionCode' => 'MissingParameterValue', 'locator' => 'service']
            );
        }
        if ($req->operation === '') {
            throw new OwsException(
                'No request',
                attributes: ['exceptionCode' => 'MissingParameterValue', 'locator' => 'request']
            );
        }
    }

    /**
     * @throws OwsException
     */
    private function checkLayerEnabled(Request $req): void
    {
        if (empty($req->typeNames)) return;
        $model = $this->ctx->model();
        foreach ($req->typeNames as $tn) {
            $row = $model->getGeometryColumns("{$this->ctx->schema}.$tn", '*');
            if (empty($row['enableows'])) {
                throw new OwsException(
                    'Layer is not enabled',
                    attributes: ['exceptionCode' => 'InvalidParameterValue', 'locator' => 'typename']
                );
            }
        }
    }

    /**
     * @throws ServiceException
     * @throws Throwable
     * @throws InvalidArgumentException
     */
    private function basicAuthPerLayer(Request $req): void
    {
        if ($this->ctx->trusted || empty($req->typeNames)) return;
        $model = $this->ctx->model();
        $isTransaction = $req->operation === 'TRANSACTION';
        foreach ($req->typeNames as $tn) {
            $auth = $model->getGeometryColumns("{$this->ctx->schema}.$tn", 'authentication');
            $needsAuth = $auth === 'Read/write'
                || ($isTransaction && ($auth === 'Write' || $auth === 'Read/write'))
                || !empty(Input::getAuthUser());
            if ($needsAuth) {
                new BasicAuth()->authenticate("{$this->ctx->schema}.$tn", $isTransaction);
            }
        }
    }
}
