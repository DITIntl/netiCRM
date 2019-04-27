<style>{literal}
.tp-wrapper {
  max-width: 480px;
  width: auto;
  margin: 8px auto;
  display: flex;
  justify-content: space-between;
  align-content: center;
  flex-wrap: nowrap;
  position: relative;
}
.tp-card-expiration-date {
  width: 100%;
}
.tp-label {
  margin-right: 10px;
}
.tp-field {
  height: 40px;
  border: 1px solid gray;
  margin: 5px 0;
  padding: 5px;
  position: relative;
}
.tp-card {
  width: 30px;
  height: 20px;
}
.tp-field.card-number {
  width: 100%;
}
.tp-field.card-expiration-date{
  width: 100%;
}
.tp-field.card-ccv{
  width: 80px;
}
.tp-field .overlay {
  position: absolute;
  width: 100%;
  height: 100%;
  z-index: 99;
  left: 0;
  top: 0;
  cursor: not-allowed;
}
.crm-button-type-upload,
.crm-button-type-upload .form-submit{
  margin:0;
  width: 100%;
  height: 40px;
}
.tappay-field-focus {
  border-color: #66afe9;
  outline: 0;
  -webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, .075), 0 0 8px rgba(102, 175, 233, .6);
  box-shadow: inset 0 1px 1px rgba(0, 0, 0, .075), 0 0 8px rgba(102, 175, 233, .6);
}
#card-number {
  padding-left: 55px;
}
#loading {
  position: absolute;
  right: 10px;
  top: 30%;
}
{/literal}</style>
<div class="tp-wrapper">
  <label class="tp-label">信用卡卡號</label>
  <div>
    <img src="https://js.tappaysdk.com/tpdirect/image/visa.svg" alt="" class="tp-card">
    <img src="https://js.tappaysdk.com/tpdirect/image/mastercard.svg" class="tp-card">
    <img src="https://js.tappaysdk.com/tpdirect/image/jcb.svg" alt="" class="tp-card">
    <img src="https://js.tappaysdk.com/tpdirect/image/amex.svg" alt="" class="tp-card">
  </div>
</div>
<div class="tp-wrapper">
  <div class="tp-field card-number" id="card-number">
    <img src="https://js.tappaysdk.com/tpdirect/image/card.svg" alt="" style="width: 30px; position: absolute; left: 10px;" id="card-type-img">
  </div>
</div>
<div class="tp-wrapper">
  <div {if $hideccv}class="tp-card-expiration-date"{/if}>
    <label class="tp-label">到期月年</label>
    <div class="tp-field card-expiration-date" id="card-expiration-date"></div>
  </div>
  {if !$hideccv}
  <div>
    <label class="tp-label">檢查碼</label>
    <div class="tp-field card-ccv" id="card-ccv"></div>
  </div>
  {/if}
</div>
<div class="tp-wrapper">
  <div class="crm-button crm-button-type-upload"><form id="payment" name="payment"><input id="make-payment" class="form-submit" value="{ts}Make Payment{/ts}" type="submit" disabled="disabled" style="display:none;"></form></div>
</div>
<div class="tp-wrapper" id="error-message">
</div>
<script src="https://js.tappaysdk.com/tpdirect/v4"></script>
<script>{literal}
cj(document).ready(function($){
  var appID = '{/literal}{$payment_processor.signature}{literal}';
  var appKey = '{/literal}{$payment_processor.subject}{literal}';
  var qfKey = '{/literal}{$qfKey}{literal}';
  var className = '{/literal}{$class_name}{literal}';
  var endpoint = '{/literal}{crmURL p="civicrm/tappay/paybyprime"}{literal}';
  var redirect = '{/literal}{$redirect}{literal}';
  var failRedirect = '{/literal}{$fail_redirect}{literal}';
  var contributionID = {/literal}{$contribution_id}{literal};
  var request = '';
  var lock = false;
  var submitted = getCookie(qfKey);
  if (submitted == "1" || submitted >= 1) {
    $("#make-payment").remove();
    $('.tp-wrapper').hide();
    $("#error-message").html('<span>{/literal}{ts 1=$backlink}Order submitted. You can create another <a href="%1">here</a>.{/ts}{literal}</span>').show();
    return;
  }
  else {
    $("#make-payment").show();
  }

  if (appID.length <= 0 && appKey.length <= 0) {
    return;
  }

  // prevent close tab
  window.onbeforeunload = function(){
    return true;
  };

  appID = parseInt(appID);
  TPDirect.setupSDK(appID, appKey, '{/literal}{if $payment_processor.is_test}sandbox{else}production{/if}{literal}');

  TPDirect.card.setup({
    fields: {
      number: {
        // css selector
        element: '#card-number',
        placeholder: '____ ____ ____ ____'
      },
      expirationDate: {
        // DOM object
        element: document.getElementById('card-expiration-date'),
        placeholder: 'MM / YY'
      },
      ccv: {
        element: '#card-ccv',
        placeholder: '000'
      }
    },
    styles: {
      // Style all elements
      'input': {
        'color': '#555555'
      },
      // Styling ccv field
      'input.cvc': {
        'font-size': '16px'
      },
      // Styling expiration-date field
      'input.expiration-date': {
        'font-size': '16px'
      },
      // Styling card-number field
      'input.card-number': {
        'font-size': '16px'
      },
      // style focus state
      ':focus': {
        'color': 'black'
      },
      // style valid state
      '.valid': {
        'color': 'green'
      },
      // style invalid state
      '.invalid': {
        'color': 'red'
      },
      // Media queries
      // Note that these apply to the iframe, not the root window.
      '@media screen and (max-width: 400px)': {
        'input': {
          'color': 'orange'
        }
      }
    }
  });


  TPDirect.card.onUpdate(function (update) {
    if (update.canGetPrime && !lock) {
      $("#make-payment").removeProp("disabled");
    }
    else {
      $("#make-payment").prop("disabled", 1);
    }

    // cardTypes = ['mastercard', 'visa', 'jcb', 'amex', 'unknown']
    var src = $("#card-type-img").prop("src").replace(/[^\/]*\.svg$/, '');
    if (typeof update.cardType === 'string' && update.cardType !== 'unknown' && update.cardType.length > 0) {
      // Handle card type visa.
      $("#card-type-img").prop("src", src + update.cardType + '.svg');
    }
    else {
      $("#card-type-img").prop("src", src + 'card.svg');
    }
  });

  $('#payment').on('submit', function(e){
    e.preventDefault();
    $("#make-payment").prop("disabled", 1); // prevet double submit

    const tappayStatus = TPDirect.card.getTappayFieldsStatus();
    if (tappayStatus.canGetPrime === true) {
      window.onbeforeunload = null;
      TPDirect.card.getPrime(function(result)  {
        window.onbeforeunload = function(){ return true; };
        if (result.status !== 0) {
          $("#make-payment").removeProp("disabled"); // prevet double submit
        }
        else {
          lock = true;
          $("#make-payment").prop("disabled", 1); // prevet double submit
          $("form#payment").append('<i class="zmdi zmdi-spinner zmdi-hc-spin" id="loading"></i>');
          $(".tp-field").prepend('<div class="overlay"></div>');

          // send prime to server
          $.ajax({
            type: "POST",
            url: endpoint,
            data: "id="+contributionID+"&qfKey="+qfKey+'&'+'class='+className+'&prime='+result.card.prime,
            dataType: 'json' 
          })
          // redirect to thank you page
          .done(function(data) {
            $("#loading").remove();
            if (data.status === 0) {
              setCookie(qfKey, 1, 3600);
              window.onbeforeunload = null;
              window.location.href = redirect;
            }
            else {
              $(".tp-field .overlay").remove();
              $("#make-payment").removeProp("disabled");
              $("#error-message").html('<div>'+data.msg+'</div>');
              setCookie(qfKey, 1, 3600);
              window.onbeforeunload = null;
              window.location.href = failRedirect;
            }
          })
          .fail(function() {
            $("#loading").remove();
            $(".tp-field .overlay").remove();
            $("#make-payment").removeProp("disabled");
          });

        }
      });
    }
    else {
      alert('Something goes wrong, please reload window and try again.');
      return;
    }
  });
});
{/literal}</script>