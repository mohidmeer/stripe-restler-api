# stripe-restler-api
Many functions require data from frontened like getCustomer stripeID which can be obtained from backend via userToken

```php
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



```
## After subscription or checkout Session or customer create you have to add the desired logic to update stripe with your backend database
```php
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


```
