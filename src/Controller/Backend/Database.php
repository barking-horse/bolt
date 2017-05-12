<?php

namespace Bolt\Controller\Backend;

use Bolt\Form\FormType\DatabaseCheckType;
use Bolt\Response\TemplateResponse;
use Bolt\Response\TemplateView;
use Bolt\Storage\Migration\Processor\SchemaProcessor;
use Bolt\Storage\Migration\Processor\TableRecordsProcessor;
use Silex\ControllerCollection;
use Symfony\Component\Debug\BufferingLogger;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Backend controller for database manipulation routes.
 *
 * Prior to v3.0 this functionality primarily existed in the monolithic
 * Bolt\Controllers\Backend class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Database extends BackendBase
{
    protected function addRoutes(ControllerCollection $c)
    {
        $c->match('/dbcheck', 'check')
            ->bind('dbcheck');

        $c->post('/dbupdate', 'update')
            ->bind('dbupdate');

        $c->get('/dbupdate_result', 'updateResult')
            ->bind('dbupdate_result');
    }

    /**
     * Check the database for missing tables and columns.
     *
     * Does not do actual repairs.
     *
     * @param Request $request
     *
     * @return TemplateResponse|TemplateView|RedirectResponse
     */
    public function check(Request $request)
    {
        $output = null;
        $changes = null;
        /** @var Form $form */
        $form = $this->createFormBuilder(DatabaseCheckType::class)
            ->getForm()
        ;
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $button = $form->getClickedButton();
            if ($button->getName() === 'update') {
                $output = $this->schemaManager()->update();
                $changes = $output->getResponseStrings();
            } elseif ($button->getName() === 'upgrade') {
                $logger = new BufferingLogger();

                /** @var SchemaProcessor $transformer */
                $transformer = $this->app['data_type.transformer.schema'];
                $transformer->setLogger($logger);
                $transformer->transform();
                /** @var TableRecordsProcessor $transformer */
                $transformer = $this->app['data_type.transformer.table_records'];
                $transformer->setLogger($logger);
                $transformer ->transform();

                foreach ($logger->cleanLogs() as $key => $change) {
                    $changes[$key] = $change[1];
                }
            } elseif ($button->getName() === 'show_changes') {
                return $this->redirectToRoute('dbcheck', ['debug' => true]);
            } elseif ($button->getName() === 'hide_changes') {
                return $this->redirectToRoute('dbcheck');
            }
        }


        /** @var $schema \Bolt\Storage\Database\Schema\Manager */
        $schema = $this->app['schema'];
        $check = $schema->check();
        /** @var \Bolt\Storage\Database\Schema\Comparison\BaseComparator $comparator */
        $comparator = $this->app['schema.comparator'];

        $context = [
            'changes' => $changes,
            'check'   => $output ? null : $check,
            'debug'   => $request->query->has('debug'),
            'alters'  => $comparator->getAlters(),
            'creates' => $comparator->getCreates(),
            'diffs'   => $comparator->getDiffs(),
            'form'    => $form->createView()
        ];

        return $this->render('@bolt/dbcheck/dbcheck.twig', $context);
    }

    /**
     * Check the database, create tables, add missing/new columns to tables.
     *
     * @deprecated Don't use.
     *
     * @return RedirectResponse
     */
    public function update()
    {
        $output = $this->schemaManager()->update();
        $this->session()->set('dbupdate_result', $output->getResponseStrings());

        return $this->redirectToRoute('dbupdate_result');
    }

    /**
     * Show the result of database updates.
     *
     * @deprecated Don't use.
     *
     * @return TemplateResponse
     */
    public function updateResult()
    {
        $output = $this->session()->get('dbupdate_result', []);

        $context = [
            'changes' => $output,
            'check'   => null,
            'debug'   => false,
            'alters'  => null,
            'creates' => null,
            'diffs'   => null,
        ];

        return $this->render('@bolt/dbcheck/dbcheck.twig', $context);
    }

    /**
     * @return \Bolt\Storage\Database\Schema\Manager
     */
    protected function schemaManager()
    {
        return $this->app['schema'];
    }
}
