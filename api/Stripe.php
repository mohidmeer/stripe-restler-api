<?php


namespace api;

use Stripe\Customer;
use Stripe\StripeClient;

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
                'price' =>'price_1NlAG7LRl3FQzHrDmM7De9z9' ,
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
        $products = $this->stripe->products->all(['limit'=>5]);
        foreach($products->data as $p){
            $price=$this->stripe->prices->retrieve($p->default_price); 
            $response[]=[
                'id'=>$p->id,
                'name'=>$p->name,
                'image'=>$p->metadata->image,
                'sku'=>$p->metadata->sku,
                'stripePriceId'=>$p->default_price,
                'price'=>$price->unit_amount/100
            ];
        }
        return $response;
    }

    }
