/* global location flw_payment_args jQuery*/
'use strict';

// var form   = jQuery( '#flw-pay-now-button' );

// if ( form ) {

//   form.on( 'click', function( evt ) {
//     evt.preventDefault();
//     location.href = flw_payment_args.cb_url;
//   } );

// }

// 'use strict';

// var raveLogo = 'https://res.cloudinary.com/dkbfehjxf/image/upload/v1511542310/Pasted_image_at_2017_11_09_04_50_PM_vc75kz.png'
var amount = flw_payment_args.amount,
    cbUrl  = flw_payment_args.cb_url,
    country = flw_payment_args.country,
    curr   = flw_payment_args.currency,
    desc   = flw_payment_args.desc,
    email  = flw_payment_args.email,
    firstname = flw_payment_args.firstname,
    lastname = flw_payment_args.lastname,
    form   = jQuery( '#flw-pay-now-button' ),
    // logo   = flw_payment_args.logo || raveLogo,
    p_key  = flw_payment_args.p_key,
    title  = flw_payment_args.title,
    txref  = flw_payment_args.txnref,
    paymentOptions = flw_payment_args.payment_options,
    paymentStyle  = flw_payment_args.payment_style,
    disableBarter  = flw_payment_args.barter,
    redirect_url;

if ( form ) {

  form.on( 'click', function( evt ) {
    evt.preventDefault();
    if(paymentStyle == 'inline'){
      processPayment();
    }else{
      location.href = flw_payment_args.cb_url;
    }
  } );

}

//switch country base on currency
switch (curr) {
  case 'KES':
    country = 'KE';
    break;
  case 'GHS':
    country = 'GH';
    break;
  case 'ZAR':
    country = 'ZA';
    break;
  case 'TZS':
    country = 'TZ';
    break;

  default:
    country = 'NG';
    break;
}

var processPayment = function() {
  // console.log(firstname+" .......... "+lastname);

  // setup payload
  var ravePayload = {
    amount: amount,
    country: country,
    currency: curr,
    custom_description: desc,
    custom_title: title,
    // custom_logo: logo,
    customer_email: email,
    customer_firstname: firstname,
    customer_lastname: lastname,
    txref: txref,
    payment_options: paymentOptions,
    PBFPubKey: p_key,
    onclose: function() {},
    callback: function(response){
      if ( response.tx.chargeResponseCode == "00" || response.tx.chargeResponseCode == "0" ) {
          // popup.close();
                      
          redirectPost(cbUrl,response.tx);
      }else{
          alert(response.respmsg);
      }

      popup.close(); // close modal
    }
  }

  // disable barter or not
  if(disableBarter == 'yes'){
    ravePayload.disable_pwb = true;
  }

  // add payload
  var popup = getpaidSetup(ravePayload);

};

var sendPaymentRequestResponse = function( res ) {
  jQuery
    .post( cbUrl, res.tx )
    .success( function(data) {
      var response = JSON.parse( data );
      redirect_url = response.redirect_url;
      setTimeout( redirectTo, 5000, redirect_url );
    } );
};

//redirect function
var redirectPost = function(location, args){
  // console.log(args);
    var form = '';
    jQuery.each( args, function( key, value ) {
        // value = value.split('"').join('\"')
        form += '<input type="hidden" name="'+key+'" value="'+value+'">';
    });
    jQuery('<form action="' + location + '" method="POST">' + form + '</form>').appendTo(jQuery(document.body)).submit();
}

var redirectTo = function( url ) {
  location.href = url;
};
