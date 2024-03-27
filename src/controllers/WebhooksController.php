<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\controllers;

use Craft;
use craft\helpers\App;
use craft\shopify\Plugin;
use craft\web\assets\admintable\AdminTableAsset;
use craft\web\Controller;
use Shopify\Rest\Admin2023_10\Webhook;
use Shopify\Webhooks\Registry;
use Shopify\Webhooks\Topics;
use yii\web\ConflictHttpException;
use yii\web\Response as YiiResponse;

/**
 * The WebhooksController to manage the Shopify webhooks.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class WebhooksController extends Controller
{
    /**
     * Edit page for the webhook management
     *
     * @return YiiResponse
     */
    public function actionEdit(): YiiResponse
    {
        $view = $this->getView();
        $view->registerAssetBundle(AdminTableAsset::class);

        if (!$session = Plugin::getInstance()->getApi()->getSession()) {
            throw new ConflictHttpException('No Shopify API session found, check credentials in settings.');
        }
        
        // Had to downgrade from Craft4: TODO fix "Create" button logic
        
        $webhooks = Webhook::all($session);
    
        
        $containsAllWebhooks = true;
        
        if ($webhooks) {
            foreach ($webhooks as $item) {
    
                if (str_contains($item->address, Craft::$app->getRequest()->getHostName())) {
                    $containsAllWebhooks = false;
                    continue;
                }
                    
                if (!in_array($item->topic, [
                    'products/create',
                    'products/delete',
                    'products/update',
                    'inventory_levels/update',
                    'products/create'
                ])) {
                    $containsAllWebhooks = false;
                    continue;
                }
            }
        } else {
            $containsAllWebhooks = false;
        }
        

        // If we don't have all webhooks needed for the current environment show the create button
        return $this->renderTemplate('shopify/webhooks/index', compact('webhooks', 'containsAllWebhooks'));
    }

    /**
     * Creates the webhooks for the current environment.
     *
     * @return YiiResponse
     */
    public function actionCreate(): YiiResponse
    {
        $this->requirePostRequest();

        $view = $this->getView();
        $view->registerAssetBundle(AdminTableAsset::class);

        $pluginSettings = Plugin::getInstance()->getSettings();

        if (!$session = Plugin::getInstance()->getApi()->getSession()) {
            throw new ConflictHttpException('No Shopify API session found, check credentials in settings.');
        }

        $responseCreate = Registry::register(
            path: 'shopify/webhook/handle',
            topic: Topics::PRODUCTS_CREATE,
            shop: App::parseEnv($pluginSettings->hostName),
            accessToken: App::parseEnv($pluginSettings->accessToken)
        );
        $responseUpdate = Registry::register(
            path: 'shopify/webhook/handle',
            topic: Topics::PRODUCTS_UPDATE,
            shop: App::parseEnv($pluginSettings->hostName),
            accessToken: App::parseEnv($pluginSettings->accessToken)
        );
        $responseDelete = Registry::register(
            path: 'shopify/webhook/handle',
            topic: Topics::PRODUCTS_DELETE,
            shop: App::parseEnv($pluginSettings->hostName),
            accessToken: App::parseEnv($pluginSettings->accessToken)
        );

        $responseInventoryUpdate = Registry::register(
            path: 'shopify/webhook/handle',
            topic: Topics::INVENTORY_LEVELS_UPDATE,
            shop: App::parseEnv($pluginSettings->hostName),
            accessToken: App::parseEnv($pluginSettings->accessToken)
        );

        if (!$responseCreate->isSuccess() || !$responseUpdate->isSuccess() || !$responseDelete->isSuccess() || !$responseInventoryUpdate->isSuccess()) {
            Craft::error('Could not register webhooks with Shopify API.', __METHOD__);
        }

        $this->setSuccessFlash(Craft::t('app', 'Webhooks registered.'));
        return $this->redirectToPostedUrl();
    }

    /**
     * Deletes a webhook from the Shopify API.
     *
     * @return YiiResponse
     */
    public function actionDelete(): YiiResponse
    {
        $this->requireAcceptsJson();
        $id = Craft::$app->getRequest()->getBodyParam('id');

        if ($session = Plugin::getInstance()->getApi()->getSession()) {
            Webhook::delete($session, $id);
            return $this->asSuccess(Craft::t('shopify', 'Webhook deleted'));
        }

        return $this->asSuccess(Craft::t('shopify', 'Webhook could not be deleted'));
    }
}
