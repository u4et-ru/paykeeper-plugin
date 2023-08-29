<?php declare(strict_types=1);

namespace Plugin\Paykeeper;

use App\Domain\AbstractExtension;
use App\Domain\Entities\Catalog\Order;
use App\Domain\Entities\User;
use App\Domain\Service\Catalog\Exception\OrderNotFoundException;

class PaykeeperPluginTwigExt extends AbstractExtension
{
    public function getName()
    {
        return 'rb_plugin';
    }

    public function getFunctions()
    {
        return [
            new \Twig\TwigFunction('pk_form', [$this, 'pk_form'], ['is_safe' => ['html']]),
            new \Twig\TwigFunction('pk_link', [$this, 'pk_link'], ['is_safe' => ['html']]),
        ];
    }

    public function pk_form(Order $order)
    {
        if (!str_contains($order->getSystem(), 'Заказ оплачен')) {
            $host = $this->parameter('PaykeeperPlugin_host', 'https://demo.paykeeper.ru');
            $products = [];

            foreach ($order->getProducts() as $product) {
                if ($product->getPrice() > 0) {
                    $products[] = [
                        'name' => $product->getTitle(),
                        'price' => $product->getPrice(),
                        'quantity' => $product->getCount(),
                        'sum' => $product->getCount() * $product->getPrice(),
                        'tax' => $this->parameter('PaykeeperPlugin_tax', 'none'),
                        'item_type' => $product->getType() === 'product' ? 'goods' : $product->getType(),
                    ];
                }
            }

            $orderid = $order->getExternalId() ? str_replace('-s', '', $order->getExternalId()) : $order->getSerial();

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-type: application/x-www-form-urlencoded',
                    'content' => http_build_query([
                        'clientid' => $order->getDelivery()['client'],
                        'client_email' => $order->getEmail(),
                        'client_phone' => $order->getPhone(),
                        'orderid' => $orderid,
                        'cart' => json_encode($products),
                        'sum' => $order->getTotalPrice(),
                    ]),
                ]
            ]);

            return file_get_contents($host . '/order/inline/', false, $context);
        }

        return '';
    }

    public function pk_link(Order $order)
    {
        $login = $this->parameter('PaykeeperPlugin_login', 'demo');
        $password = $this->parameter('PaykeeperPlugin_password', 'demo');
        $host = $this->parameter('PaykeeperPlugin_host', 'https://demo.paykeeper.ru');
        $orderid = $order->getExternalId() ? str_replace('-s', '', $order->getExternalId()) : $order->getSerial();

        // заголовки
        $header = implode("\r\n", [
            'Content-type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode("$login:$password"),
        ]);

        // шаг 1: получение токена
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $header,
            ]
        ]);

        $result = file_get_contents($host . '/info/settings/token/', false, $context);
        $result = json_decode($result, true);

        if (!empty($result['token'])) {
            $homepage = $this->parameter('common_homepage', '');
            $description = $this->parameter('PaykeeperPlugin_description', '');
            $products = [];

            foreach ($order->getProducts() as $product) {
                if ($product->getPrice() > 0) {
                    $products[] = [
                        'name' => $product->getTitle(),
                        'price' => $product->getPrice(),
                        'quantity' => $product->getCount(),
                        'sum' => $product->getCount() * $product->getPrice(),
                        'tax' => $this->parameter('PaykeeperPlugin_tax', 'none'),
                        'item_type' => $product->getType() === 'product' ? 'goods' : $product->getType(),
                    ];
                }
            }

            // шаг 2: получение ссылки
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => $header,
                    'content' => http_build_query([
                        'clientid' => $order->getDelivery()['client'],
                        'client_email' => $order->getEmail(),
                        'client_phone' => $order->getPhone(),
                        'orderid' => $orderid,
                        'service_name' => json_encode([
                            'cart' => $products,
                            'service_name' => $description,
                            'user_result_callback' => $homepage . '/cart/done/' . $order->getUuid()->toString(),
                        ]),
                        'pay_amount' => $order->getTotalPrice(),
                        'token' => $result['token'],
                    ]),
                ]
            ]);

            $result = file_get_contents($host . '/change/invoice/preview/', false, $context);
            $result = json_decode($result, true);

            if (!empty($result['invoice_id'])) {
                return $result['invoice_url'];
            }
        }

        return null;
    }
}
