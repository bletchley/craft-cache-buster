<?php
/**
 * Cache Buster plugin for Craft CMS 3.x
 *
 * Purges cache from Fastly after an element is changed
 *
 * @link      http://bletchley.co
 * @copyright Copyright (c) 2018 Andy Skogrand
 */

namespace bletchley\cachebuster;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\services\Fields;
use craft\events\RegisterComponentTypesEvent;

use craft\base\Element;
use craft\elements\db\ElementQuery;
use craft\events\ElementEvent;
use craft\events\ElementStructureEvent;
use craft\events\MoveElementEvent;
use craft\events\PopulateElementEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\SectionEvent;
use craft\events\TemplateEvent;
use craft\services\Elements;
use craft\services\Sections;
use craft\services\Structures;

use yii\base\Event;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;

/**
 * Class CacheBuster
 *
 * @author    Andy Skogrand
 * @package   CacheBuster
 * @since     1.0.0
 *
 */
class CacheBuster extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var CacheBuster
     */
    public static $plugin;

    // Private Properties
    // =========================================================================

    protected $serviceId;
    protected $apiToken;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->serviceId = getenv('FASTLY_SERVICE_ID');
        $this->apiToken = getenv('FASTLY_API_KEY');

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
        );

        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, function ($event) {
            $this->handleUpdateEvent($event);
        });
        Event::on(Element::class, Element::EVENT_AFTER_MOVE_IN_STRUCTURE, function ($event) {
            $this->handleUpdateEvent($event);
        });
        Event::on(Elements::class, Elements::EVENT_AFTER_DELETE_ELEMENT, function ($event) {
            $this->handleUpdateEvent($event);
        });
        Event::on(Structures::class, Structures::EVENT_AFTER_MOVE_ELEMENT, function ($event) {
            $this->handleUpdateEvent($event);
        });
        Event::on(Sections::class, Sections::EVENT_AFTER_SAVE_SECTION, function ($event) {
            $this->handleUpdateEvent($event);
        });

        Craft::info(
            Craft::t(
                'cache-buster',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

    protected function handleUpdateEvent(Event $event)
    {

        if(!$this->apiToken || !$this->serviceId) {
            return false;
        }

        $client = new Client([
            'base_uri' => 'https://api.fastly.com',
            'headers'  => [
                'Content-Type' => 'application/json',
                'Fastly-Key'   => $this->apiToken
            ]
        ]);

        try {
            Craft::info('Clearing Fastly Cache');
            $client->request('POST', "service/{$this->serviceId}/purge_all");
        } catch (BadResponseException $e) {
            Craft::warning($e);
            return false;
        }

        return true;
    }

}
