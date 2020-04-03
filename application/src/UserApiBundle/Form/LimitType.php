<?php

namespace UserApiBundle\Form;

use Symfony\Component\Form\{
    AbstractType,
    Extension\Core\Type\NumberType,
    FormBuilderInterface,
    FormError,
    FormEvent,
    FormEvents
};
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class LimitType
 * @package UserApiBundle\Form
 */
class LimitType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('charge_limit_enabled', null, [ 'required' => true ])
            ->add('monthly_limit', NumberType::class, [
                'scale' => 1,
            ])
            ->add('warn_limit_reached');

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            if (!isset($data['charge_limit_enabled'])) {
                return;
            }

            $data['charge_limit_enabled'] = $this->checkBoolean($data['charge_limit_enabled']);
            if (is_string($data['charge_limit_enabled'])) {
                $form->addError(new FormError('The charge_limit_enabled value is not a valid boolean type.'));
            }

            $event->setData($data);

            if (!isset($data['warn_limit_reached'])) {
                return;
            }

            $data['warn_limit_reached'] = $this->checkBoolean($data['warn_limit_reached']);
            if (is_string($data['warn_limit_reached'])) {
                $form->addError(new FormError('The warn_limit_reached value is not a valid boolean type.'));
            }

            $event->setData($data);
        });
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'UserApiBundle\Model\Limit',
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

    private function checkBoolean($value)
    {
        if (in_array($value, ['0', 'false'], true)) {
            $value = 0;
        }

        if (in_array($value, ['1', 'true'], true)) {
            $value = 1;
        }

        return $value;
    }
}
