<?php

namespace UserApiBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserUpdateType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('firstName', TextType::class, ['required' => false, 'trim'=>true])
            ->add('lastName', TextType::class, ['required' => false, 'trim'=>true])
            ->add('phoneNumber', TextType::class, ['required' => false, 'trim'=>true])
            ->add('website', TextType::class, ['required' => false, 'trim'=>true]);
    }/**
 * {@inheritdoc}
 */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'UserApiBundle\Entity\User',
            'csrf_protection' => false,
            'validation_groups' => 'update'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return '';
    }


}