<?php

if(!function_exists('route_is')){
    function route_is($route=null){
        if(Request::routeIs($route)){
            return true;
        }else{
            return false;
        }
    }
}

if(!function_exists('route_is')){
    function route_is($routes=[]){
        foreach($routes as $route){
            if(Request::routeIs($route)){
                return true;
            }else{
                return false;
            }
        }
    }
}

if(!function_exists('notify')){
    function notify($message , $type='success'){
        return array(
            'message'=> $message,
            'alert-type' => $type,
        );
    }
}

if(!function_exists('alert')){
    function alert($message , $type='success'){
        return array(
            'message'=> $message,
            'alert-type' => $type,
        );
    }
}

if (!function_exists('currency_symbol')) {
    /**
     * The currency symbol to print wherever a price already shows one, for
     * the currently active business (session('business_id')). Falls back
     * to the app-wide `app_currency` setting for contexts with no active
     * business (e.g. before business setup, or if none was ever selected).
     */
    function currency_symbol(): string
    {
        $businessId = session('business_id');
        $business = $businessId ? \App\Models\Business::find($businessId) : null;

        return $business ? $business->currencySymbol() : settings('app_currency', 'Rs');
    }
}
