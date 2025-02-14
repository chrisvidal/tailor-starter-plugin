<?php namespace Artistro08\TailorStarter;

use Backend;
use Event;
use Log;
use Validator;
use Mail;
use System\Classes\PluginBase;
use Tailor\Models\EntryRecord;
use Tailor\Models\GlobalRecord;
use View;

/**
 * Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'Tailor Starter',
            'description' => 'A companion plugin to go with the Tailor Starter Theme.',
            'author'      => 'Artistr08',
            'icon'        => 'icon-archive'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register() {
        $this->app->resolving('validator', function($validator) {
            Validator::extend('recaptcha', 'Artistro08\TailorStarter\Classes\ReCaptchaValidator@validateReCaptcha', 'Recaptcha validation failed. Please refresh and try again.');
        });
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return void
     */
    public function boot()
    {
        EntryRecord::extend(function($model) {
            $model->bindEvent('model.afterSave', function() use ($model) {
                if ($model->inSection('Content\Orders')) {

                    // Get the settings from the site
                    $settings = GlobalRecord::findForGlobal('Content\Settings');

                    // Get the order statuses
                    $order_status      = $model->order_status;
                    $sent_receipt      = $model->sent_email_receipt;
                    $sent_in_progress  = $model->sent_in_progress;
                    $sent_cancelled    = $model->sent_cancelled;
                    $sent_tracking     = $model->sent_tracking_receipt;
                    $resend_email      = $model->resend_email;

                    // Remove Tailor ID from metadata
                    $unwanted_words = 'tailor_id';
                    $replace_match  = '/^.*' . $unwanted_words . '.*$(?:\r\n|\n)?/m';
                    $order_contents = preg_replace($replace_match, '', $model->order_contents);

                    // Set Mail Order Data
                    View::share('site_name', $settings->website_name);
                    $mail_data = [
                        'customer_name'        => $model->customer_name,
                        'customer_email'       => $model->customer_email,
                        'shipping_method'      => $model->shipping_method,
                        'customer_address'     => $model->customer_address,
                        'tracking_number'      => $model->tracking_number,
                        'tracking_url'         => $model->tracking_url,
                        'cancellation_message' => $model->cancellation_message,
                        'total'                => $model->total,
                        'order_contents'       => $order_contents,
                    ];

                    // New Order
                    if($order_status == 'new' && !$sent_receipt) {

                        // Send Customer Email
                        Mail::send('artistro08.tailorstarter::mail.new_order', $mail_data, function($message) use ($model) {
                            $message->to($model->customer_email, $model->customer_name);
                        });

                        // Send Admin Email
                        Mail::send('artistro08.tailorstarter::mail.new_order_admin', $mail_data, function($message) use ($settings, $model) {
                            $message->to($settings->notification_email, $model->notification_email_recipient_name);
                        });

                        $model->sent_email_receipt = true;
                        $model->save();
                    }

                    // Order in Progress
                    if($order_status == 'in_progress' && !$sent_in_progress) {

                        // Send Customer Email
                        Mail::send('artistro08.tailorstarter::mail.order_in_progress', $mail_data, function($message) use ($model) {
                            $message->to($model->customer_email, $model->customer_name);
                        });

                        $model->sent_in_progress = true;
                        $model->save();
                    }

                    // Shipped Order
                    if($order_status == 'shipped' && !$sent_tracking) {

                        // Send Customer Email
                        Mail::send('artistro08.tailorstarter::mail.order_shipped', $mail_data, function($message) use ($model) {
                            $message->to($model->customer_email, $model->customer_name);
                        });

                        $model->sent_tracking_receipt = true;
                        $model->save();
                    }

                    // Cancelled Order
                    if($order_status == 'cancelled' && !$sent_cancelled) {

                        // Send Customer Email
                        Mail::send('artistro08.tailorstarter::mail.order_cancelled', $mail_data, function($message) use ($model) {
                            $message->to($model->customer_email, $model->customer_name);
                        });

                        $model->sent_cancelled = true;
                        $model->save();
                    }

                    // Check if we need to resend any emails
                    if($resend_email) {

                        // New Order
                        if($order_status == 'new') {

                            // Send Customer Email
                            Mail::send('artistro08.tailorstarter::mail.new_order', $mail_data, function($message) use ($model) {
                                $message->to($model->customer_email, $model->customer_name);
                            });

                            $model->resend_email = false;
                            $model->save();
                        }

                        // Order in Progress
                        if($order_status == 'in_progress') {

                            // Send Customer Email
                            Mail::send('artistro08.tailorstarter::mail.order_in_progress', $mail_data, function($message) use ($model) {
                                $message->to($model->customer_email, $model->customer_name);
                            });

                            $model->resend_email = false;
                            $model->save();
                        }

                        // Shipped Order
                        if($order_status == 'shipped') {

                            // Send Customer Email
                            Mail::send('artistro08.tailorstarter::mail.order_shipped', $mail_data, function($message) use ($model) {
                                $message->to($model->customer_email, $model->customer_name);
                            });

                            $model->resend_email = false;
                            $model->save();
                        }

                        // Cancelled Order
                        if($order_status == 'cancelled') {

                            // Send Customer Email
                            Mail::send('artistro08.tailorstarter::mail.order_cancelled', $mail_data, function($message) use ($model) {
                                $message->to($model->customer_email, $model->customer_name);
                            });

                            $model->resend_email = false;
                            $model->save();
                        }

                    }
                }
            });
        });

    }

    public function registerMailTemplates()
    {
        return [
            'artistro08.tailorstarter::mail.new_order',
            'artistro08.tailorstarter::mail.new_order_admin',
            'artistro08.tailorstarter::mail.order_cancelled',
            'artistro08.tailorstarter::mail.order_in_progress',
            'artistro08.tailorstarter::mail.order_shipped',
            'artistro08.tailorstarter::mail.form_submission'
        ];
    }

    /**
     * Registers any front-end components implemented in this plugin.
     *
     * @return array
     */
    public function registerComponents()
    {
        return []; // Remove this line to activate

        return [
            'Artistr08\TailorStarter\Components\MyComponent' => 'myComponent',
        ];
    }

    /**
     * Registers any backend permissions used by this plugin.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return []; // Remove this line to activate

        return [
            'artistr08.tailorstarter.some_permission' => [
                'tab' => 'TailorStarter',
                'label' => 'Some permission'
            ],
        ];
    }

    /**
     * Registers backend navigation items for this plugin.
     *
     * @return array
     */
    public function registerNavigation()
    {
        return []; // Remove this line to activate

        return [
            'tailorstarter' => [
                'label'       => 'TailorStarter',
                'url'         => Backend::url('artistr08/tailorstarter/mycontroller'),
                'icon'        => 'icon-leaf',
                'permissions' => ['artistr08.tailorstarter.*'],
                'order'       => 500,
            ],
        ];
    }
}
