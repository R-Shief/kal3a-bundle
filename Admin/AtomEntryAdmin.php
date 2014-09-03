<?php
namespace Rshief\Bundle\Kal3aBundle\Admin;

use Sonata\AdminBundle\Admin\Admin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

/**
 * Class AtomEntryAdmin
 * @package Rshief\Bundle\Kal3aBundle\Admin
 */
class AtomEntryAdmin extends Admin
{
    protected $baseRoutePattern = '/atom';
    protected $baseRouteName = 'admin_bangpound_atom_entry';

    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('id')
            ->add('categories')
            ->add('published', null, array(), 'datetime', [
                'format' => 'Y-m-d H:i:s.u'
            ])
        ;
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('id', null, array('sortable' => false))
            ->add('title', null, array('sortable' => false))
            ->add('lang', null, array('sortable' => false))
            ->add('published', null, array('sortable' => false))
        ;
    }

    /**
     * @param FormMapper $formMapper
     */
    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
            ->add('id')
        ;
    }

    /**
     * @param ShowMapper $showMapper
     */
    protected function configureShowFields(ShowMapper $filter)
    {
        $filter
            ->add('id')
            ->add('title')
            ->add('attachment')
        ;
    }
}
