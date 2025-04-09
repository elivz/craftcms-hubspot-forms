<?php

namespace jordanbeattie\hubspotforms\services;

use craft\base\Component;
use Craft;
use GuzzleHttp\Client;
use jordanbeattie\hubspotforms\HubspotForms;

class HubspotFormsService extends Component
{

    /*
     * Get Forms
     */
    public function getForms()
    {

        return Craft::$app->cache->getOrSet( 'hubspot-forms::forms', function() {

            /* Create forms array */
            $forms = [];

            /* Set API URL */
            $limit = Craft::$app->plugins->getPlugin('hubspot-forms')->getSettings()->getHsLimit() ?? 100;
            $link = "https://api.hubapi.com/marketing/v3/forms?limit={$limit}";

            while( true )
            {

                /* Send request to HubSpot */
                $request = $this->sendRequest( $link );
                
                /* Exit on failure */
                if( is_null( $request ) || !$request->getStatusCode() == "200" )
                {
                    break;
                }

                /* Decode response */
                $response = json_decode( $request->getBody()->getContents() );

                /* Loop forms from API request */
                foreach( $response->results as $form )
                {
                    /* Add form to forms array */
                    /* Key = name, Value = ID */
                    $forms[ $form->name ] = $form->id;
                }

                /* Check if there are more pages */
                if( !property_exists( $response, 'paging' ) || !isset( $response->paging->next->link ) )
                {
                    break;
                }
                
                /* Set API URL */
                $link = $response->paging->next->link;
                
            }

            /* Sort alphabetically */
            ksort( $forms );

            /* Return forms array */
            return $forms;

        }, 600 );
        
    }

    /*
     * Get Portal ID
     */
    public function getPortalId( $token = null )
    {

        return Craft::$app->cache->getOrSet( 'hubspot-forms::portal', function() {
        
            /* Send request */
            $request = $this->sendRequest( "https://api.hubapi.com/integrations/v1/me", $token );

            /* Handle invalid response */
            if( is_null( $request ) ){ return null; }

            /* Get portal id from response */
            return json_decode( $request->getBody()->getContents() )->portalId ?? null;

        }, 3600 );

    }

    /*
     * Check settings
     */
    public function hasValidSettings(): bool
    {

        /* Get settings */
        $settings = Craft::$app->plugins->getPlugin('hubspot-forms')->getSettings();

        /* Return token & portalId set */
        return ( $settings->getHsToken() && $settings->getHsPortalId() );

    }

    /*
     * Forms URL
     */
    public function getFormsUrl()
    {
        return "https://app.hubspot.com/forms/{$this->getPortalId()}";
    }

    /*
     * Send request
     * Interact with the HubSpot API
     */
    private function sendRequest( $url, $token = null )
    {
        Craft::info("Sending request to " . $url);
        
        try
        {

            /* Get HubSpot token */
            if( is_null( $token ) )
            {
                $token = HubspotForms::getInstance()->settings->getHsToken();
            }
            
            /* Create HTTP Client */
            $request = new Client();
    
            /* Send request with token */
            return $request->get( $url, [ 'headers' => [
                "Authorization" => "Bearer {$token}",
                "Accept" => "application/json",
            ]]);
            
        }
        catch( \Throwable $th )
        {

            /* Return null upon error */
            return null;

        }
    }

}