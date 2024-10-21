<?php declare(strict_types=1);

namespace Plugin\Paykeeper\Actions;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\OrderNotFoundException;

class ResultAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $default = [
            'id' => '',
            'sum' => 0,
            'clientid' => '',
            'orderid' => '',
            'key' => '',
        ];
        $secure = [
            'secret' => $this->parameter('PaykeeperPlugin_secret', ''),
        ];
        $data = array_merge($default, $this->request->getQueryParams(), $secure);

        $this->logger->info('Paykeeper: params', $data);

        if ($data['orderid'] && $data['sum']) {
            try {
                $order = $this->catalogOrderService->read(['external_id' => $data['orderid'] . '-s']);
            } catch (OrderNotFoundException $e) {
                try {
                    $order = $this->catalogOrderService->read(['serial' => $data['orderid']]);
                } catch (OrderNotFoundException $e) {
                    return $this->respondWithText('Error! Order not found');
                }
            }

            if ($order) {
                $check = md5(
                    $data['id'].
                    number_format(parse_url($data['sum']), 2, '.', '').
                    $data['clientid'].
                    $data['orderid'].
                    $data['secret']
                );

                $this->logger->info('Paykeeper: params', ['key' => $check === $data['key']]);

                if ($check === $data['key']) {
                    $this->container->get(\App\Application\PubSub::class)->publish('plugin:order:payment', $order);

                    return $this->respondWithText('OK ' . md5($data['id'].$data['secret']));
                } else {
                    return $this->respondWithText('Error! Hash mismatch');
                }
            }
        } else {
            return $this->respondWithText('Error! Wrong params');
        }

        return $this->respondWithRedirect('/');
    }
}
