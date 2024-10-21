<?php declare(strict_types=1);

namespace Plugin\Paykeeper;

use App\Domain\AbstractPaymentPlugin;
use Psr\Container\ContainerInterface;
use App\Domain\Models\CatalogOrder;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class PaykeeperPlugin extends AbstractPaymentPlugin
{
    const AUTHOR = 'ilshatkin';
    const NAME = 'PaykeeperPlugin';
    const TITLE = 'Paykeeper';
    const AUTHOR_SITE = 'https://u4et.ru';
    const VERSION = '2.0.0';

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->addSettingsField([
            'label' => 'Адрес формы',
            'type' => 'text',
            'name' => 'host',
        ]);

        $this->addSettingsField([
            'label' => 'Логин',
            'type' => 'text',
            'name' => 'login',
        ]);

        $this->addSettingsField([
            'label' => 'Пароль',
            'type' => 'text',
            'name' => 'password',
        ]);

        $this->addSettingsField([
            'label' => 'Секретное слово',
            'type' => 'text',
            'name' => 'secret',
        ]);

        $this->addSettingsField([
            'label' => 'Налоговая ставка',
            'type' => 'select',
            'name' => 'tax',
            'args' => [
                'option' => [
                    'none' => 'Без НДС',
                    'vat0' => 'НДС чека по ставке 10%',
                    'vat10' => 'НДС чека по ставке 10%',
                    'vat20' => 'НДС чека по расчетной ставке 20/120',
                    'vat110' => 'НДС чека по расчетной ставке 20/120',
                    'vat120' => 'НДС чека по расчетной ставке 20/120',
                ],
            ],
        ]);

        $this->addSettingsField([
            'label' => 'Описание к оплате',
            'type' => 'text',
            'name' => 'description',
        ]);

        // успешная оплата
        $this
            ->map([
                'methods' => ['get', 'post'],
                'pattern' => '/cart/done/pk/result',
                'handler' => \Plugin\Paykeeper\Actions\ResultAction::class,
            ])
            ->setName('common:pk:result');
    }

    public function getRedirectURL(CatalogOrder $order): ?string
    {
        $login = $this->parameter('PaykeeperPlugin_login', 'demo');
        $password = $this->parameter('PaykeeperPlugin_password', 'demo');
        $host = $this->parameter('PaykeeperPlugin_host', 'https://demo.paykeeper.ru');
        $orderid = $order->external_id ? str_replace('-s', '', $order->external_id) : $order->serial;

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

        if ($result) {
            $result = json_decode($result, true);

            if (!empty($result['token'])) {
                $homepage = $this->parameter('common_homepage', '');
                $description = $this->parameter('PaykeeperPlugin_description', '');
                $products = [];

                foreach ($order->products as $product) {
                    if ($product->price > 0) {
                        $products[] = [
                            'name' => $product->title,
                            'price' => $product->totalPrice(),
                            'quantity' => $product->totalCount(),
                            'sum' => $product->totalSum(),
                            'tax' => $this->parameter('PaykeeperPlugin_tax', 'none'),
                            'item_type' => $product->type === 'product' ? 'goods' : $product->type,
                        ];
                    }
                }

                // шаг 2: получение ссылки
                $context = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => $header,
                        'content' => http_build_query([
                            'clientid' => $order->delivery['client'],
                            'client_email' => $order->email,
                            'client_phone' => $order->phone,
                            'orderid' => $orderid,
                            'service_name' => json_encode([
                                'cart' => $products,
                                'service_name' => $description,
                                'user_result_callback' => $homepage . 'cart/done/' . $order->uuid,
                            ]),
                            'pay_amount' => $order->totalSum(),
                            'token' => $result['token'],
                        ]),
                    ]
                ]);

                $result = file_get_contents($host . '/change/invoice/preview/', false, $context);

                if ($result) {
                    $result = json_decode($result, true);

                    if (!empty($result['invoice_id'])) {
                        return $result['invoice_url'];
                    }
                }
            }
        }

        return null;
    }
}
