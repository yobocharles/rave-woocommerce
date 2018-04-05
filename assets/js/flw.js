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

var raveLogo = 'https://res.cloudinary.com/dkbfehjxf/image/upload/v1511542310/Pasted_image_at_2017_11_09_04_50_PM_vc75kz.png'
var amount = flw_payment_args.amount,
    cbUrl  = flw_payment_args.cb_url,
    country = flw_payment_args.country,
    curr   = flw_payment_args.currency,
    desc   = flw_payment_args.desc,
    email  = flw_payment_args.email,
    form   = jQuery( '#flw-pay-now-button' ),
    logo   = flw_payment_args.logo || raveLogo,
    p_key  = flw_payment_args.p_key,
    title  = flw_payment_args.title,
    txref  = flw_payment_args.txnref,
    paymentMethod  = flw_payment_args.payment_method,
    paymentStyle  = flw_payment_args.payment_style,
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

var processPayment = function() {

 var popup = getpaidSetup({
    amount: amount,
    country: country,
    currency: curr,
    custom_description: desc,
    custom_title: title,
    custom_logo: logo,
    customer_email: email,
    txref: txref,
    payment_method: paymentMethod,
    PBFPubKey: p_key,
    onclose: function() {},
    callback: function(response){
      if ( response.tx.chargeResponseCode == "00" || response.tx.chargeResponseCode == "0" ) {
          // popup.close();
                      
          redirectPost(cbUrl,response.tx);
      }else{
          alert(response.respmsg);
      }
    }
  });

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
var redirectPost = function(location, args){
  console.log(args);
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
