<?php

namespace Bolt\Provider;

use Bolt\Collection\MutableBag;
use Bolt\Storage\Migration;
use Bolt\Storage\Migration\Transformer;
use Silex\Application;
use Silex\ServiceProviderInterface;

/**
 * Bolt database type migration service provider.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
final class DatabaseTypeMigrationServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Application $app)
    {
        $app['data_type.type_batch_count'] = 1000;

        $app['data_type.type_transformers'] = $app->share(function () {
            return MutableBag::from([
                new Transformer\ImageTypeTransformer(),
                new Transformer\NullableTypeTransformer(),
            ]);
        });

        $app['data_type.transformer.table_records'] = $app->share(
            function ($app) {
                return new Migration\Processor\TableRecordsProcessor(
                    $app['storage'],
                    $app['schema'],
                    $app['data_type.type_transformers'],
                    $app['monolog'],
                    $app['data_type.type_batch_count']
                );
            }
        );

        $app['data_type.transformer.schema'] = $app->share(
            function ($app) {
                return new Migration\Processor\SchemaProcessor(
                    $app['storage'],
                    $app['schema']
                );
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app)
    {
    }
}
