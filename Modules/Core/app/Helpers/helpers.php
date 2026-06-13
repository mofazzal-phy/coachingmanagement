<?php
if (!function_exists('api_response')) {
    function api_response($data = null, $message = 'Success', $code = 200) {
        return response()->json(['status'=> $code<400?'success':'error','message'=>$message,'data'=>$data], $code);
    }
}