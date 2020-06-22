<?php

/**
 * Class crmIptelefonPlugin
 * @author BNP
 * @email support@byloneprosto.ru inohacker@yandex.ru
 */
class crmIptelefonsuPlugin extends waPlugin
{

    public function backendAssetsHandler(&$params)
    {
        $version = $this->info['version'];
        if (waSystemConfig::isDebug()) {
            $version .= '.'.filemtime($this->path.'/js/iptelefonsu.js');
        }
        return '<script type="text/javascript" src="'.$this->getPluginStaticUrl().'js/iptelefonsu.js?v'.$version.'"></script>';
    }

    public function backendAssets()
    {
        $settings = $this->getSettings();
        if (!empty($settings['pbx_url'])) {
            return $settings['pbx_url'];
        }
        if (!empty($settings['pbx_key'])) {
            return $settings['pbx_key'];
        }
        if (!empty($settings['crm_key'])) {
            return $settings['crm_key'];
        }
    }

    /**
     * @return array
     * @throws waException
     */
    protected function getSettingsConfig()
    {
        if (wa()->getUser()->isAdmin()) {
            return parent::getSettingsConfig();
        }

        return array();
    }

    /**
     * @param array $settings
     * @return array|void
     * @throws waException
     */
    public function saveSettings($settings = array())
    {
        if (wa()->getUser()->isAdmin()) {
            return parent::saveSettings($settings);
        }
    }
}
