<?php

namespace GP247\Shop\Api\Front;

use GP247\Front\Controllers\RootFrontController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use GP247\Shop\Models\ShopOrder;
use Illuminate\Support\Facades\Validator;
use GP247\Shop\Controllers\Auth\AuthTrait;

class MemberAuthController extends RootFrontController
{
    use AuthTrait;

    /**
     * Login user and create token
     *
     * @param  [string] email
     * @param  [string] password
     * @param  [boolean] remember_me
     * @return [string] access_token
     * @return [string] token_type
     * @return [string] expires_at
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
            'remember_me' => 'boolean'
        ]);
        $credentials = request(['email', 'password']);

        if (!$this->guard()->attempt($credentials)) {
            return response()->json([
                'error' => 1,
                'msg' => 'Unauthorized'
            ], 401);
        }

        $user = $this->guard()->user();

        if ($user->status == 0) {
            $scope = explode(',', config('gp247-config.api.auth.api_scope_user_guest'));
        } else {
            $scope = explode(',', config('gp247-config.api.auth.api_scope_user'));
        }
        
        $tokenResult = $user->createToken('Client:'.$user->email.'- '.now(), $scope);
        $token = $tokenResult->plainTextToken;
        $accessToken = $tokenResult->accessToken;
        if ($request->remember_me) {
            $accessToken->expires_at = Carbon::now()->addDays(config('gp247-config.api.auth.api_remmember'));
        } else {
            $accessToken->expires_at = Carbon::now()->addDays(config('gp247-config.api.auth.api_token_expire_default'));
        }
        $accessToken->save();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'scopes' => $accessToken->abilities,
            'expires_at' => Carbon::parse(
                $accessToken->expires_at
            )->toDateTimeString()
        ]);
    }


    /**
     * Validate data input
     */
    protected function validator(array $data)
    {
        $dataMapp = $this->mappingValidator($data);
        return Validator::make($data, $dataMapp['validate'], $dataMapp['messages']);
    }

  
    /**
     * Logout user (Revoke the token)
     *
     * @return [string] message
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'error' => 0,
            'msg' => 'Successfully logged out'
        ]);
    }

    public function getInfo(Request $request)
    {
        return response()->json($request->user());
    }
  
    protected function guard()
    {
        return Auth::guard('customer');
    }

    public function getOrderList(Request $request)
    {
        $customer = $request->user();
        if ($customer) {
            $orders = (new ShopOrder)
            ->with('details')
            ->with('orderTotal')
            ->where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->jsonPaginate();
            return response()->json($orders, 200);
        }
        return response()->json([
            'error' => 1,
            'msg' => 'User not found'
        ], 401);
    }

    public function getOrderDetail(Request $request, $id)
    {
        $customer = $request->user();
        if ($customer) {
            $order = (new ShopOrder)
            ->with('details')
            ->with('orderTotal')
            ->where('customer_id', $customer->id)
            ->where('id', $id)
            ->first();
            return response()->json($order, 200);
        }
        return response()->json([
            'error' => 1,
            'msg' => 'User not found'
        ], 401);
    }
}
