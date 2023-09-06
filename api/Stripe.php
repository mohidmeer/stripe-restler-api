<?php


namespace api;

use Stripe\Customer;
use Stripe\StripeClient;
use Stripe\Webhook;

class Stripe {
    public $stripe;
    public function __construct()
    {
        $this->stripe=new StripeClient([
            "api_key" => "sk_test_51NjxUbLRl3FQzHrDqQdKd246y4RftUid8f3Zppl7R6JlA4F0ICoJcOdwi9q3C3YTskkP3rNaVX1J4PSCzae4aGCr00svLMs9pT",
             "stripe_version" => "2023-08-16"
        ]);
    }
    /**
    * 
    * @param string $email
    * @url GET customer/{email}
    */
    function getCustomer($email){

        // check if customer exists
        $customer = $this->stripe->customers->search([
            'query' => "email:'$email'",'limit'=>1
        ]);
        
        // if user exists then return the customerID of user otherwise create new
        if (!(count($customer['data'])==0)){

            return $customer['data'][0]->id;
        }

        // create a new customer
        $customerObject= $this->stripe->customers->create(['email'=>$email]);
        sleep(8);
        return $customerObject->id;

    }

    function postPaymentMethod($id){
        $ephemereal=$this->stripe->ephemeralKeys->create(
        ['customer'=>$id],
        ['stripe_version' => '2023-08-16']
        );
        $setupIntent = $this->stripe->setupIntents->create([
            'customer'=>$id,
            'payment_method_types'=>['card'],
            'usage'=> 'off_session',
            'use_stripe_sdk'=>'true'
        ]);
        $response = ['ephemeralKey'=>$ephemereal->secret,'setupIntent'=>$setupIntent->client_secret];
        return $response;
    }
    
    function postSubscription($request_data){
        $ephemereal=$this->stripe->ephemeralKeys->create(
            ['customer'=>$request_data['id']],
            ['stripe_version' => '2023-08-16']
            );
        $sub = $this->stripe->subscriptions->create([
            'customer' => $request_data['id'],
            'items' => [[
                'price' =>'price_1NnHlsLRl3FQzHrDOWalavhs' ,
                 'quantity'=>$request_data['quantity']
            ]],
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
            'expand' => ['latest_invoice.payment_intent'],
        ]);

        $response = ['ephemeralKey'=>$ephemereal->secret,'setupIntent'=>$sub->latest_invoice->payment_intent->client_secret];
        return $response;
    }

    function postPay($request_data){
        
        $amount=0;
        // Saving This Cart In STRIPE metadata stripe metadata has limit of 500 characters so cannot save the full cart just saving stripe price id
        // and qunatity for later use
        $cart=[];
        foreach ($request_data['cart'] as $item) {
            $price =$this->stripe->prices->retrieve($item["stripePriceId"]);
            $amount+=($price->unit_amount*$item["quantity"]);
            $cart[]=[
                "StripepriceId" => $item["stripePriceId"],
                "quantity" => $item["quantity"]
            ];
        }
        $ephemereal=$this->stripe->ephemeralKeys->create(
            ['customer'=>$request_data['customerId']],
            ['stripe_version' => '2023-08-16']
            );
        
        $pay = $this->stripe->paymentIntents->create([
            'customer' => $request_data['customerId'],
            'amount'=>$amount,
            'currency'=>'usd',
            'metadata'=>['cart' => json_encode($cart),'diliver_address'=>json_encode($request_data['address'])]
          ]);


        $response = ['ephemeralKey'=>$ephemereal->secret,'setupIntent'=>$pay->client_secret];
        
        return $response;
    }
    function postCheckout($request_data){
        $cart=[];
        foreach ($request_data['cart'] as $item) {
            $cart[]=[
                "price" => $item["stripePriceId"],
                "quantity" => $item["quantity"]
            ];
        }
        $checkout = $this->stripe->checkout->sessions->create([
            'customer'=>$request_data['customerId'],
            'success_url' => 'https://example.com/success',
            'line_items' => $cart,
            'mode' => 'payment',
            'shipping_address_collection'=>[
                'allowed_countries'=>['US','CA']
            ]
          ]);

          return $checkout->url;
    }

    function getProducts(){
        $response=[];
        $products = $this->stripe->products->all(['limit'=>8]);
        foreach($products->data as $p){
            $price=$this->stripe->prices->retrieve($p->default_price); 
            $response[]=[
                'id'=>$p->id,
                'name'=>$p->name,
                'image'=>$p->metadata->image,
                'sku'=>$p->metadata->sku,
                'stripePriceId'=>$p->default_price,
                'price'=>$price->unit_amount/100,
                'category'=>$p->metadata->category,
                'unit'=>$p->metadata->unit,
            ];
        }
        return $response;
    }
    function getSubscriptionPrice(){
            $price=$this->stripe->prices->retrieve('price_1NnHlsLRl3FQzHrDOWalavhs'); 
           
        return $price->unit_amount/100;
    }
    
    function postSubscribeMachine($request_data){
        $ephemereal=$this->stripe->ephemeralKeys->create(
            ['customer'=>$request_data['id']],
            ['stripe_version' => '2023-08-16']
            );
        $sub = $this->stripe->subscriptions->create([
            'customer' => $request_data['id'],
            'items' => [[
                'price' =>'price_1NnHlsLRl3FQzHrDOWalavhs' ,
            ]],
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => ['machineLabel'=>$request_data['machineLabel']]
        ]);

        $response = ['ephemeralKey'=>$ephemereal->secret,'setupIntent'=>$sub->latest_invoice->payment_intent->client_secret];
        return $response;
    }
    /**
    * 
    * @param string $label
     * @url GET machinesubscription/{label}
    */
    function getMachinesubscription($label){
        $quotedLabel = "'" . $label . "'";
        $response =$this->stripe->subscriptions->search([
            'query' =>"metadata['machineLabel']:$quotedLabel",
            'limit'=>1
          ]);
        if (count($response->data)==0){
            $res = [ 
                'status'=>'new',
            ];
            return $res;
        }
        if ($response->data[0]->status=='canceled'){
            $res = [ 
                'status'=>$response->data[0]->status,
                'id'=>$response->data[0]->id,
            ];
            return $res;

        }
        $res = [ 
            'status'=>($response->data[0]->cancellation_details->reason==null) ? $response->data[0]->status : $response->data[0]->cancellation_details->reason,
            'id'=>$response->data[0]->id,
        ];
        return $res;
    }
    function getCancelMachineSubscription($id){
        $res= $this->stripe->subscriptions->update(
            $id,
            [
                'cancel_at_period_end'=> true
            ]);

        return $res;
    }
    function getResumeMachineSubscription($id){
        $res= $this->stripe->subscriptions->update(
            $id,
            [
                'cancel_at_period_end'=> false
            ]);

        return $res;
        return $res;
    }

    function postStripeWebhook(){
        $payload = file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $webhookSecret = 'whsec_Ox6oQRn1GHYkJ0Lb6g6OqmNJxfHVpqS1';
        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            http_response_code(400);
            return ['error' => 'Invalid payload'];
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            http_response_code(400);
            return ['error' => 'Invalid signature'];
        }
        // I included all the events called when stripe calls the webhook for each event you will be given an object of that specific event you can use that object to update
        // status in database
        switch ($event->type) {
            case 'charge.captured':
              $charge = $event->data->object;
            //   if you want to collect the payments records for customer for subscritionms and checkout session 
            case 'charge.failed':
              $charge = $event->data->object;
            case 'checkout.session.async_payment_failed':
              $session = $event->data->object;
            //   if payment fails for any reasson
            case 'checkout.session.async_payment_succeeded':
              $session = $event->data->object;
            //   if payment succedes then you obtain the shipping address items and quantity 
            case 'checkout.session.completed':
              $session = $event->data->object;
            // case 'checkout.session.expired':
            //   $session = $event->data->object;
            case 'customer.created':
              $customer = $event->data->object;
            //   here you might want to save the stripe customerID to database for latter use if customer is created successfully 
            case 'customer.subscription.created':
              $subscription = $event->data->object;
            //   here you will get the machineLabel from {$subscription->metadata->machineLabel}
            //  you want to udpadte the machine status 
            case 'customer.subscription.deleted': 
              $subscription = $event->data->object;
            case 'customer.subscription.pending_update_applied':
              $subscription = $event->data->object;
            case 'customer.subscription.pending_update_expired':
              $subscription = $event->data->object;
            // case 'setup_intent.canceled':
            // $setupIntent = $event->data->object;
            // case 'setup_intent.created':
            // $setupIntent = $event->data->object;
            // case 'setup_intent.requires_action':
            // $setupIntent = $event->data->object;
            // case 'setup_intent.setup_failed':
            // $setupIntent = $event->data->object;
            case 'setup_intent.succeeded':
            $setupIntent = $event->data->object;
              // here you want to save in database if user paayment is saved
            default:
              echo 'Received unknown event type ' . $event->type;
          }
        
        // Respond with a success status code
        http_response_code(200);
        return ['message' => 'Webhook event processed successfully'];
    }
    
    }
  
