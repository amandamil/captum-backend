<?php

namespace UserApiBundle\Form;

use Symfony\Component\{
    Form\AbstractType,
    Form\Extension\Core\Type\NumberType,
    Form\Extension\Core\Type\TextType,
    Form\FormBuilderInterface,
    OptionsResolver\OptionsResolver
};

/**
 * Class BalanceType
 * @package UserApiBundle\Form
 */
class AmountType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('payment_method_nonce', TextType::class, [
                'required' => true,
            ])
            ->add('balance_amount', NumberType::class, [
                'required' => true,
                'scale' => 1,
                'attr' => [
                    'min' => 20,
                ],
            ]);
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'UserApiBundle\Model\Amount',
            'csrf_protection' => false
        ]);
    }

    /**
     * @return string|null
     */
    public function getBlockPrefix()
    {
        return '';
    }
}
