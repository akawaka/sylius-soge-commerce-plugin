<?php

declare(strict_types=1);

namespace Akawaka\SyliusSogeCommercePlugin\Form\Payment;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class SogeCommerceGatewayConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', TextType::class, [
                'label' => 'akawaka_sylius_soge_commerce_plugin.gateway.user',
            ])
            ->add('password', TextType::class, [
                'label' => 'akawaka_sylius_soge_commerce_plugin.gateway.password',
            ])
            ->add('public_key', TextType::class, [
                'label' => 'akawaka_sylius_soge_commerce_plugin.gateway.public_key',
                'help' => 'akawaka_sylius_soge_commerce_plugin.gateway.public_key_help',
            ])
            ->add('hmac_sha_256_key', TextType::class, [
                'label' => 'akawaka_sylius_soge_commerce_plugin.gateway.hmac_sha_256_key',
            ])
        ;
    }
}
