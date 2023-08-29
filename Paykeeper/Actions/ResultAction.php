<?php declare(strict_types=1);

namespace Plugin\Paykeeper\Actions;

use App\Application\Actions\Cup\Catalog\CatalogAction;

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

        $order = $this->catalogOrderService->read(['external_id' => $data['orderid'] . '-s']);

        if ($order) {
            $check = md5(
                $data['id'].
                number_format(parse_url($data['sum']), 2, '.', '').
                $data['clientid'].
                $data['orderid'].
                $data['secret']
            );

            if ($check === $data['key']) {
                $status = $this->catalogOrderStatusService->read(['title' => 'Оплачен']);

                $this->catalogOrderService->update($order, [
                    'status' => $status ?? null,
                    'system' => 'Заказ оплачен',
                ]);

                $this->container->get(\App\Application\PubSub::class)->publish('tm:order:oplata', $order);

                return $this->respondWithText('OK '.md5($data['id'].$data['secret']));
            } else {
                return $this->respondWithText('Error! Hash mismatch');
            }
        }

        return $this->respondWithRedirect('/');
    }
}
