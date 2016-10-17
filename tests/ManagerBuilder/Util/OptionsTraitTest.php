<?php

/*
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder).
 * Doctrine2 managers builder.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Doctrine\ManagerBuilder\Tests\Util;

use Doctrine\Common\Proxy\AbstractProxyFactory;
use Jgut\Doctrine\ManagerBuilder\Util\OptionsTrait;

/**
 * Options trait tests.
 */
class OptionsTraitTest extends \PHPUnit_Framework_TestCase
{
    public function testOptions()
    {
        $options = [
            'proxies_namespace' => 'MyTestProxyNamespace',
            'proxies_auto_generation' => AbstractProxyFactory::AUTOGENERATE_ALWAYS,
        ];

        /* @var OptionsTrait $optionsTrait */
        $optionsTrait = $this->getMockBuilder(OptionsTrait::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['getOptions', 'getOption', 'hasOption', 'setOptions', 'setOption'])
            ->getMockForTrait();

        $optionsTrait->setOptions($options);

        self::assertEquals($options, $optionsTrait->getOptions());
        self::assertTrue($optionsTrait->hasOption('proxies_auto_generation'));
        self::assertEquals('MyTestProxyNamespace', $optionsTrait->getOption('proxies_namespace'));

        $optionsTrait->setOption('proxies_path', [__DIR__]);
        self::assertEquals([__DIR__], $optionsTrait->getOption('proxies_path'));
    }
}
