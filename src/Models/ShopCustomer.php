<?php

namespace GP247\Shop\Models;

use GP247\Shop\Models\ShopCustomerAddress;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Auth;
use GP247\Core\Models\AdminCustomFieldDetail;
use Laravel\Sanctum\HasApiTokens;

class ShopCustomer extends Authenticatable
{
    use \GP247\Core\Models\ModelTrait;
    use \GP247\Core\Models\UuidTrait;
    
    use Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = GP247_DB_PREFIX.'shop_customer';
    protected $guarded = [];
    protected $connection = GP247_DB_CONNECTION;
    private static $profile = null;
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];
    protected $appends = [
        'name',
    ];
    public function orders()
    {
        return $this->hasMany(ShopOrder::class, 'customer_id', 'id');
    }

    public function addresses()
    {
        return $this->hasMany(ShopCustomerAddress::class, 'customer_id', 'id');
    }

    /**
     * Send email reset password
     * @param  [type] $token [description]
     * @return [type]        [description]
     */
    public function sendPasswordResetNotification($token)
    {
        $emailReset = $this->getEmailForPasswordReset();
        return gp247_customer_sendmail_reset_notification($token, $emailReset);
    }

    /*
    Full name
     */
    public function getNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }



    protected static function boot()
    {
        parent::boot();
        // before delete() method call this
        static::deleting(
            function ($customer) {
                
                //Delete custom field
                (new \GP247\Core\Models\AdminCustomFieldDetail)
                ->join(GP247_DB_PREFIX.'admin_custom_field', GP247_DB_PREFIX.'admin_custom_field.id', GP247_DB_PREFIX.'admin_custom_field_detail.custom_field_id')
                ->where(GP247_DB_PREFIX.'admin_custom_field_detail.rel_id', $customer->id)
                ->where(GP247_DB_PREFIX.'admin_custom_field.type', 'shop_customer')
                ->delete();
            }
        );

        //Uuid
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = gp247_generate_id('CUS');
            }
        });
    }


    /**
     * Update info customer
     * @param  [array] $dataUpdate
     * @param  [int] $id
     */
    public static function updateInfo($dataUpdate, $id)
    {
        $dataClean = gp247_clean($dataUpdate);

        $fields = $dataClean['fields'] ?? [];
        unset($dataClean['fields']);

        $user = self::find($id);
        $user->update($dataClean);

        //Insert custom fields
        gp247_custom_field_update($fields, $user->id, 'shop_customer');

        return $user;
    }

    /**
     * Create new customer
     * @return [type] [description]
     */
    public static function createCustomer(array $dataInsert)
    {
        $dataClean = gp247_clean($dataInsert);

        $fields = $dataClean['fields'] ?? [];
        unset($dataClean['fields']);

        $dataAddress = gp247_customer_address_mapping($dataClean)['dataAddress'];
        
        $user = self::create($dataClean);
        $address = $user->addresses()->save(new ShopCustomerAddress($dataAddress));
        $user->address_id = $address->id;
        $user->save();

        //Insert custom fields
        gp247_custom_field_update($fields, $user->id, 'shop_customer');
        
        // Process event customer created
        gp247_event_customer_created($user);
        
        return $user;
    }

    /**
     * Get address default of user
     *
     * @return  [collect]
     */
    public function getAddressDefault()
    {
        return (new ShopCustomerAddress)->where('customer_id', $this->id)
            ->where('id', $this->address_id)
            ->first();
    }

    public function profile()
    {
        if (self::$profile === null) {
            self::$profile = customer()->user();
        }
        return self::$profile;
    }

    /**
     * Check customer has Check if the user is verified
     *
     * @return boolean
     */
    public function isVerified()
    {
        return ! is_null($this->email_verified_at)  || $this->provider_id ;
    }

    /**
     * Check customer need verify email
     *
     * @return boolean
     */
    public function hasVerifiedEmail()
    {
        return !$this->isVerified() && gp247_config('customer_verify');
    }
    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerify()
    {
        if ($this->hasVerifiedEmail()) {
            return gp247_customer_sendmail_verify($this->email, $this->id);
        }
        return false;
    }
}
