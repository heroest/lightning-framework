<?php

namespace Lightning\Http;

use Symfony\Component\OptionsResolver\OptionsResolver;
use function Lightning\config;

class Payload
{
    public $method;
    public $url;
    public $headers = [];
    public $postField = null;
    private $options = [];
    private static $optionResolver;

    public function __construct()
    {
        self::initOptionResolver();
    }

    public function setOptions(array $options)
    {
        $this->options = self::$optionResolver->resolve($options);
    }

    public function getOptions()
    {
        return $this->options;
    }

    public static function initOptionResolver()
    {
        if (self::$optionResolver === null) {
            $config = config();
            $resolver = new OptionsResolver();
            $resolver->setDefaults([
                'timeout' => $config->get('http_client.timeout', 30),
                'follow_redirects' => true         
            ])
            ->setAllowedTypes('timeout', ['float', 'int'])
            ->setAllowedTypes('connection_timeout', ['float', 'int'])
            ->setAllowedValues('follow_redirects', [true, false]);
            self::$optionResolver = $resolver;
        }
    }
}