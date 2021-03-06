<?php

namespace App\Form;

use App\Form\Dto\FleetUpload;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class FleetUploadForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('fleetFile', FileType::class, [
            'constraints' => [
                new File(['maxSize' => '2M']),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FleetUpload::class,
            'allow_extra_fields' => true,
            'csrf_protection' => false,
        ]);
    }
}
