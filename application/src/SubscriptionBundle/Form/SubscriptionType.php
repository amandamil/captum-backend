<?php

namespace SubscriptionBundle\Form;

use SubscriptionBundle\Entity\Package;
use SubscriptionBundle\Entity\Subscription;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class SubscriptionType
 * @package SubscriptionBundle\Form
 */
class SubscriptionType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('payment_method_nonce', TextType::class, [
                'required' => false,
                'mapped' => false,
            ])
            ->add('package', EntityType::class, [
                'required' => true,
                'class' => Package::class,
            ]);
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Subscription::class,
            'csrf_protection' => false
        ));
    }

    /**
     * @return string|null
     */
    public function getBlockPrefix()
    {
        return '';
    }
}
