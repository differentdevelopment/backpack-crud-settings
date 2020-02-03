<?php

namespace Pxpm\BpSettings;

use Pxpm\BpSettings\App\Models\BpSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SettingsManager
{
    public $settings;

    public $disk;

    public $prefix;

    public function __construct()
    {
        $this->disk = 'uploads';
        $this->prefix = 'settings/';
        $this->settings = $this->getDatabaseSettings();
    }

    public function getDatabaseSettings() {
        
        return Cache::rememberForever('bp-settings', function () {
            return BpSettings::all();
        });
    }

     public function rebuildSettingsCache() {
         if (Cache::has('bp-settings')) {
             Cache::forget('bp-settings');
            $this->settings = $this->getDatabaseSettings();
         }
     }

    /**
     * Returns the setting value. It should be: setting_name.namespace.group 
     *
     * @param string $setting 
     * @return void
     */
    public function get($setting)
    {
        dd($setting);
    }

    public function settingExists($setting) {
        return $this->settings->contains('name',$setting);
    }

    public function create(array $setting) {
        $setting['type'] ?? abort(500, 'Setting need a type.');
        $setting['name'] ?? abort(500, 'Setting need a name.');
        $setting['label'] = $setting['label'] ?? $setting['name'];

        $settingOptions = array_except($setting,['type','name','label','namespace','group','value','id']);
        
        if($this->settingExists($setting['name'])) {
            $dbSetting = BpSettings::where('name',$setting['name'])->first();

            $dbSetting->update(array_except($setting,array_keys($settingOptions)));
            
        }
        
        if(!isset($dbSetting)) {
            $dbSetting = BpSettings::create([
                'name' => $setting['name'],
                'type' => $setting['type'],
                'label' => $setting['label'],
                'namespace' => $setting['namespace'] ?? null,
                'group' => $setting['group'] ?? null,

            ]);
        } 
        
        $dbSetting->options = $settingOptions;
        $dbSetting->save();

    }

    public function getFieldValidations() {
        $validations = array();
        foreach($this->settings as $setting) {
            $validations[$setting['name']] = $setting['options']['validation'] ?? null;
        }
        return array_filter($validations);
    }

    public function saveSettingsValues($settings) {
        $settingsInDb = BpSettings::whereIn('name', array_keys($settings))->get();
        foreach($settings as $settingName => $settingValue) {
            $setting = $settingsInDb->where('name',$settingName)->first();
            if (!is_null($setting)) {
                switch ($setting->type) {
                case 'image':
                    $settingValue = $this->saveImageToDisk($settingValue, $settingName);
                }

                $setting->update(['value' => $settingValue]);
            }

        }
        $this->rebuildSettingsCache();

        return true;
    }

    public function getFieldsForEditor() {
        foreach($this->settings as &$setting) {
            foreach($setting->options as $key => $option) {
                $setting->{$key} = $option;
               
            }
            unset($setting->options);
            $setting->tab = $setting->namespace ?? '';
        }
        return $this->settings->keyBy('name')->toArray();
    }

    public function update(array $setting) {
        return BpSettings::where('name',$setting['name'])->update(array_except($setting, ['name']));
    }

    public function saveImageToDisk($image,$settingName)
    {
        $setting = BpSettings::where('name',$settingName)->first();

        if ($image === null) {
            // delete the image from disk
            if(Storage::disk($this->disk)->has($setting->value))
            {
                Storage::disk($this->disk)->delete($setting->value);
            }

            // set null in the database column
            return null;
        }

        // if a base64 was sent, store it in the db
        if (starts_with($image, 'data:image'))
        {
            // 0. Make the image
            $imageCreated = \Image::make($image);

            // 1. Generate a filename.
            $filename = md5($image.time()).'.jpg';
            // 2. Store the image on disk.
            if(Storage::disk($this->disk)->has($setting->value))
            {
                Storage::disk($this->disk)->delete($setting->value);
            }

            Storage::disk($this->disk)->put($this->prefix.$filename, $imageCreated->stream());
            // 3. Save the path to the database

            return $this->prefix.$filename;
        }

        return $setting->value;
    }

    
}