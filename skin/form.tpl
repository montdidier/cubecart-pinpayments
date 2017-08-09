
<script src='https://cdn.pin.net.au/pin.v2.js'></script>
<script type="text/javascript">
	$(function() {
		// Create an API object with your publishable api key, and
		// specifying 'test' or 'live'.
		//
		// Be sure to use your live publishable key with the live api, and
		// your test publishable key with the test api.
		var pinApi = new Pin.Api('{$APIKEYPUB}', '{$PINMODE}');

		var form = $('#gateway-transfer'),
	      submitButton = form.find(":submit"),
	      errorContainer = form.find('.alert-box'),
	      errorList = errorContainer.find('ul'),
	      errorHeading = errorContainer.find('.error_title');

		// Add a submit handler to the form which calls Pin.js to
		// retrieve a card token, and then add that token to the form and
		// submit the form to your server.
		form.submit(function(e) {
			e.preventDefault();
			console.log('Flag');

			// Clear previous errors
			errorList.empty();
			errorHeading.empty();
			errorContainer.hide();

			// Disable the submit button to prevent multiple clicks
			submitButton.prop('disabled', true);

			// Fetch details required for the createToken call to Pin Payments
			var card = {
			  number:           $('#cc-number').val(),
			  name:             $('#cc-name').val(),
			  expiry_month:     $('#cc-expiry-month').val(),
			  expiry_year:      $('#cc-expiry-year').val(),
			  cvc:              $('#cc-cvc').val(),
			  address_line1:    $('#address-line1').val(),
			  address_line2:    $('#address-line2').val(),
			  address_city:     $('#address-city').val(),
			  address_state:    $('#address-state').val(),
			  address_postcode: $('#address-postcode').val(),
			  address_country:  $('#address-country').val()
			};

			// Request a token for the card from Pin Payments
			pinApi.createCardToken(card).then(handleSuccess, handleError).done();
		});

		function handleSuccess(card) {
			// Add the card token to the form
			//
			// Once you have the card token on your server you can use your
			// private key and Charges API to charge the credit card.
			$('<input>')
			  .attr('type', 'hidden')
			  .attr('name', 'card_token')
			  .val(card.token)
			  .appendTo(form);

			// Resubmit the form to the server
			//
			// Only the card_token will be submitted to your server. The
			// browser ignores the original form inputs because they don't
			// have their 'name' attribute set.
			form.get(0).submit();
		}

		function handleError(response) {
			errorHeading.text(response.error_description);

			if (response.messages) {
			  $.each(response.messages, function(index, paramError) {
			    $('<li>')
			      .text(paramError.param + ": " + paramError.message)
			      .appendTo(errorList);
			  });
			}

			errorContainer.show();
			window.location = '#pinPaymentsValError';

			// Re-enable the submit button
			submitButton.prop('disabled', false);
		};

	});
</script>

<div id="pinPaymentsValError" data-alert="" class="alert-box alert" style='display:none'>
	<div class="error_title"></div>
    <ul class="nomarg no-bullet">
    </ul>
    <a href="#" class="close">×</a>
</div>

<h2>{$LANG.orders.title_card_details}</h2>
<table width="100%" cellpadding="3" cellspacing="10" border="0">
	<tr>
		<td width="140">Name on Card</td>
		<td><input type="text" id="cc-name" name="name" value="{$CUSTOMER.name}" /></td>
	</tr>
  	<tr>
		<td width="140">{$LANG.gateway.card_number}
		<td><input type="text" id="cc-number" value="" size="16" maxlength="16" /></td>
  	</tr>
    <tr>
		<td width="140">{$LANG.gateway.card_expiry_date}</td>
		<td>
			<select id="cc-expiry-month">
			{foreach from=$CARD.months item=month}<option value="{$month.value}" {$month.selected}>{$month.display}</option>{/foreach}
    		</select> 
				/ 
			<select id="cc-expiry-year">
			{foreach from=$CARD.years item=year}<option value="{$year.value}" {$year.selected}>{$year.value}</option>{/foreach}
			</select>
		</td>
  	</tr>
  	<tr>
		<td width="140">{$LANG.gateway.card_security}
		<td><input type="text" id="cc-cvc" value="" size="5" maxlength="4" class="textbox_small" style="text-align: center" />
		<a href="images/general/cvv.gif" class="colorbox" title="{$LANG.gateway.card_security}" /> {$LANG.common.whats_this}</a>
		</td>
	</tr>
</table>
  
<h2>{$LANG.basket.customer_info}</h3>
<table width="100%" cellpadding="3" cellspacing="10" border="0">				
  	<tr>
		<td width="140">{$LANG.address.line1}</td>
		<td><input type="text" id="address-line1" value="{$CUSTOMER.add1}" size="50" /></td>
  	</tr>
  	<tr>
		<td width="140">{$LANG.address.line2}</td>
		<td><input type="text" id="address-line2" value="{$CUSTOMER.add2}" size="50" /> {$LANG.common.optional}</td>
  	</tr>
  	<tr>
		<td width="140">{$LANG.address.town}</td>
		<td><input type="text" id="address-city" value="{$CUSTOMER.city}" /></td>
  	</tr>
  	<tr>
		<td width="140">{$LANG.address.country}
		<td>
	  	<select id="address-country" >
			{foreach from=$COUNTRIES item=country}<option value="{$country.iso}"{$country.selected}>{$country.name}</option>{/foreach}
		</select>
		</td>
  	</tr>
  	<tr>
		<td width="140">{$LANG.address.state}</td>
		<td><input type="text" id="address-state" value="{$CUSTOMER.state}" size="10" /></td>
  	</tr>
  	<tr>
		<td width="140">{$LANG.address.postcode}</td>
		<td><input type="text" id="address-postcode" value="{$CUSTOMER.postcode}" size="10" maxlength="10" /></td>
  	</tr>
</table>