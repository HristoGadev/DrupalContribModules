<?php


namespace Drupal\query_builder\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create a query builder form
 */
class SelectQueryController extends ControllerBase {

    /**
     * The form builder.
     *
     * @var \Drupal\Core\Form\FormBuilderInterface
     */
    protected $formBuilder;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('form_builder')
        );
    }

    /**
     * Add formbuilder
     */
    public function __construct(FormBuilderInterface $form_builder) {
        $this->formBuilder = $form_builder;
    }

    /**
     * Return query builder form.
     */
    public function getSelectForm() {
        return $this->formBuilder->getForm('\Drupal\query_builder\Form\QueryBuilderForm');
    }

}