<?php

namespace Bolt\Form\FormType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Database check/update form type.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class DatabaseCheckType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('update', SubmitType::class)
            ->add('upgrade', SubmitType::class)
            ->add('check_again', SubmitType::class)
            ->add('show_changes', SubmitType::class)
            ->add('hide_changes', SubmitType::class)
        ;
    }
}
