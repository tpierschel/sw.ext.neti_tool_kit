<?php

/*
 * @copyright  Copyright (c) 2016, Net Inventors GmbH
 * @category   Shopware
 * @author     Net Inventors GmbH
 *
 */

namespace NetiToolKit\Subscriber;

use Enlight\Event\SubscriberInterface;
use NetiFoundation\Service\PluginManager\Config;
use NetiToolKit\Struct\PluginConfig;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\PropertyServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\BaseProduct;
use Shopware\Components\Compatibility\LegacyStructConverter;

class FrontendProperties implements SubscriberInterface
{
    /**
     * @var ContextServiceInterface
     */
    private $contextService;

    /**
     * @var PropertyServiceInterface
     */
    private $propertyService;

    /**
     * @var LegacyStructConverter
     */
    private $structConverter;

    /**
     * @var PluginConfig
     */
    private $pluginConfig;

    /**
     * FrontendProperties constructor.
     *
     * @param ContextServiceInterface  $contextService
     * @param PropertyServiceInterface $propertyService
     * @param LegacyStructConverter    $structConverter
     * @param Config                   $configService
     */
    public function __construct(
        ContextServiceInterface $contextService,
        PropertyServiceInterface $propertyService,
        LegacyStructConverter $structConverter,
        Config $configService
    ) {
        $this->contextService  = $contextService;
        $this->propertyService = $propertyService;
        $this->structConverter = $structConverter;
        $this->pluginConfig    = $configService->getPluginConfig('NetiToolKit');
    }

    /**
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Widgets_Recommendation' => 'addPropsToBought',
            'Enlight_Controller_Action_PostDispatchSecure_Widgets_Listing'        => 'addPropsToTopSellers',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Detail'        => 'onPostDispatchFrontendDetail',
            'sArticles::sGetArticlesByCategory::after'                            => 'afterGetArticlesByCategory',
        ];
    }

    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     */
    public function onPostDispatchFrontendDetail(\Enlight_Controller_ActionEventArgs $args)
    {
        if (!in_array(PluginConfig::SHOW_PROPERTIES_ON_SIMILAR_ARTICLES, $this->pluginConfig->getShowPropertiesOn())) {
            return;
        }

        /** @var \Shopware_Controllers_Frontend_Detail $subject */
        $view                         = $args->getSubject()->View();
        $sArticle                     = $view->sArticle;
        $sArticle['sSimilarArticles'] = $this->addPropertiesToArticlesArray($sArticle['sSimilarArticles']);

        $view->sArticle = $sArticle;
    }

    public function addPropsToTopSellers(\Enlight_Controller_ActionEventArgs $args)
    {
        if (!in_array($args->getRequest()->getActionName(), $this->pluginConfig->getShowPropertiesOn())) {
            return;
        }

        $view          = $args->getSubject()->View();
        $view->sCharts = $this->addPropertiesToArticlesArray($view->sCharts);
    }

    public function addPropsToBought(\Enlight_Controller_ActionEventArgs $args)
    {
        if (!in_array($args->getRequest()->getActionName(), $this->pluginConfig->getShowPropertiesOn())) {
            return;
        }

        $view                 = $args->getSubject()->View();
        $view->boughtArticles = $this->addPropertiesToArticlesArray($view->boughtArticles);
    }

    /**
     * @param \Enlight_Hook_HookArgs $args
     */
    public function afterGetArticlesByCategory(\Enlight_Hook_HookArgs $args)
    {
        if (in_array(PluginConfig::SHOW_PROPERTIES_ON_LISTING, $this->pluginConfig->getShowPropertiesOn())) {
            return;
        }

        $args->setReturn($this->addPropertiesToArticlesArray($args->getReturn()));
    }

    /**
     * @param array $articles
     *
     * @return array
     */
    private function addPropertiesToArticlesArray(array $articles)
    {
        // get property set Structs
        $legacyProps = $this->convertPropertyStructs(
            $this->propertyService->getList(
                $this->getProductStructsFromViewArticles($articles),
                $this->contextService->getShopContext()
            )
        );

        // add property arrays to sArticles array
        foreach ($articles as &$article) {
            $article['sProperties'] = $legacyProps[$article['ordernumber']];
        }
        unset($article);

        return $articles;
    }

    /**
     * @param $propertySets
     *
     * @return array
     */
    private function convertPropertyStructs($propertySets)
    {
        // convert property set Structs to legacy Array format
        $legacyProps = [];
        foreach ($propertySets as $ordernumber => $propertySet) {
            $legacyProps[$ordernumber] = $this->structConverter->convertPropertySetStruct($propertySet);
        }

        return $legacyProps;
    }

    /**
     * @param array $sArticles
     *
     * @return BaseProduct[]
     */
    private function getProductStructsFromViewArticles(array $sArticles)
    {
        //turn sArticles array into BaseProduct Structs
        $products = [];
        foreach ($sArticles as $sArticle) {
            $products[$sArticle['ordernumber']] = new BaseProduct(
                $sArticle['articleID'],
                $sArticle['articleDetailsID'],
                $sArticle['ordernumber']
            );
        }

        return $products;
    }
}
