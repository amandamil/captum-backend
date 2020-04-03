<?php

namespace ExperienceBundle\Form;

use ExperienceBundle\Entity\Experience;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class ExperienceType
 * @package ExperienceBundle\Form
 */
class ExperienceType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title')
            ->add('phone')
            ->add('email')
            ->add('contactName')
            ->add('website')
            ->add('image', FileType::class, [
                'required' => true,
                'mapped' => false,
                'constraints' => [
                    new Assert\Image([
                        'maxWidth' => Experience::IMAGE_MAX_WIDTH,
                        'maxHeight' => Experience::IMAGE_MAX_HEIGHT,
                        'maxSize' => Experience::IMAGE_MAX_FILE_SIZE,
                        'mimeTypes' => Experience::IMAGE_FILE_MIMETYPE,
                        'mimeTypesMessage' => "Please, upload a valid image (.jpg, .png)",
                    ]),
                    new Assert\NotBlank(['message' => 'Please, upload a valid image (.jpg, .png)']),
                ],
                'label' => false,
            ])
            ->add('video', FileType::class, [
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new Assert\File([
                        'maxSize' => Experience::VIDEO_MAX_SIZE,
                    ]),
                ],
                'label' => false,
            ])
            ->add('video_url',UrlType::class, [
                'required' => false,
                'mapped' => false,
            ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Experience::class,
            'csrf_protection' => false
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return '';
    }
}
