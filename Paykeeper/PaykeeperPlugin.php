<?php declare(strict_types=1);

namespace Plugin\Paykeeper;

use App\Domain\AbstractPlugin;
use Psr\Container\ContainerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class PaykeeperPlugin extends AbstractPlugin
{
    const AUTHOR = 'ilshatkin';
    const NAME = 'PaykeeperPlugin';
    const TITLE = 'Paykeeper';
    const AUTHOR_SITE = 'https://u4et.ru';
    const VERSION = '1.2';

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $self = $this;

        $this->addTwigExtension(\Plugin\Paykeeper\PaykeeperPluginTwigExt::class);

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
}
