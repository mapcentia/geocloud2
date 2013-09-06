<?php
include '../header.php';
include '../html_header.php';
($_SESSION['auth']) ? $signupBtn = "<a href='#myModal' role='button' class='btn btn-large btn-upgrade disabled'>Your plan</a>" : $signupBtn = "<a href='/user/signup' role='button' class='btn btn-large btn-warning btn-upgrade'>Get free plan</a>";
?>
<div class="container">
	<div class="row" style="margin-bottom: 20px; margin-top: 50px">
		<div class="span4">
			<div class="box">
				<h2>Free $0</h2>
				<div class='inner'>
					<ul>
						<li>
							<i class="icon-ok"></i>Up to 5 layers
						</li>
						<li>
							<i class="icon-ok"></i>Up to 5 mb
						</li>

						<li class="minus">
							<i class="icon-ok no-icon"></i>Support
						</li>
						<li class="minus">
							<i class="icon-ok no-icon"></i>Daily off-site backup
						</li>
					</ul>
					<?php echo $signupBtn; ?>
				</div>
			</div>
		</div>
		<div class="span4">
			<div class="box">
				<h2>Micro $9<span><i>/mo</i></span></h2>
				<div class='inner'>
					<ul>
						<li>
							<i class="icon-ok"></i>Up to 5 layers
						</li>
						<li>
							<i class="icon-ok"></i>Up to 15 mb
						</li>
						<li>
							<i class="icon-ok"></i>Support
						</li>
						<li class="minus">
							<i class="icon-ok no-icon"></i>Daily off-site backup
						</li>
					</ul>
					<?php
					($_SESSION['auth']) ? $btn = "<a href='#' role='button' class='btn btn-large btn-upgrade'>Upgrade</a>" : $btn = "<a href='#' role='button' class='btn btn-large btn-upgrade disabled'>Upgrade</a>";
					echo $btn;
					?>
				</div>
			</div>
		</div>
		<div class="span4">
			<div class="box">
				<h2>Small $19<span><i>/mo</i></span></h2>
				<div class='inner'>
					<ul>
						<li>
							<i class="icon-ok"></i>Up to 10 layers
						</li>
						<li>
							<i class="icon-ok"></i>Up to 50 mb
						</li>
						<li>
							<i class="icon-ok"></i>Support
						</li>
						<li>
							<i class="icon-ok"></i>Daily off-site backup
						</li>
					</ul>
					<?php
					($_SESSION['auth']) ? $btn = "<a href='#' role='button' class='btn btn-large btn-upgrade'>Upgrade</a>" : $btn = "<a href='#' role='button' class='btn btn-large btn-upgrade disabled'>Upgrade</a>";
					echo $btn;
					?>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="span4">
			<div class="box">
				<h2>Medium $39<span><i>/mo</i></span></h2>
				<div class='inner'>
					<ul>
						<li>
							<i class="icon-ok"></i>Up to 25 layers
						</li>
						<li>
							<i class="icon-ok"></i>Up to 100 mb
						</li>
						<li>
							<i class="icon-ok"></i>Support
						</li>
						<li>
							<i class="icon-ok"></i>Daily off-site backup
						</li>
					</ul>
					<?php
					($_SESSION['auth']) ? $btn = "<a href='#' role='button' class='btn btn-large btn-upgrade'>Upgrade</a>" : $btn = "<a href='#' role='button' class='btn btn-large btn-upgrade disabled'>Upgrade</a>";
					echo $btn;
					?>
				</div>
			</div>
		</div>
		<div class="span4">
			<div class="box">
				<h2>Large $99<span><i>/mo</i></span></h2>
				<div class='inner'>
					<ul>
						<li>
							<i class="icon-ok"></i>Up to 150 layers
						</li>
						<li>
							<i class="icon-ok"></i>Up to 500 mb
						</li>
						<li>
							<i class="icon-ok"></i>Support
						</li>
						<li>
							<i class="icon-ok"></i>Daily off-site backup
						</li>
					</ul>
					<?php
					($_SESSION['auth']) ? $btn = "<a href='#' role='button' class='btn btn-large btn-upgrade'>Upgrade</a>" : $btn = "<a href='#' role='button' class='btn btn-large btn-upgrade disabled'>Upgrade</a>";
					echo $btn;
					?>
				</div>
			</div>
		</div>
		<div class="span4">
			<div class="box">
				<h2>Enterprise from $199<span><i>/mo</i></span></h2>
				<div class='inner'>
					<p>
						Get your own instance of MapSentia GeoCloud. Create all the databases you need with own user management system.
					</p>
					<button class="btn btn-large btn-upgrade">
						Learn more
					</button>
				</div>
			</div>

		</div>
	</div>
	<div class="row">
		<div class="span9 all-plans">
			All plans come with… <i class="icon-ok"></i>Free setup<i class="icon-ok"></i>Private layers<i class="icon-ok"></i>All available APIs<i class="icon-ok"></i>No branding in maps
		</div>
	</div>
	<!--#include virtual="../developers/footer.html" -->
</div>
<!-- Modal -->
<div id="myModal" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">
			×
		</button>
		<h3 id="myModalLabel">Credit card information</h3>
	</div>
	<div class="modal-body">
		<div class="content">
			<form accept-charset="UTF-8" action="https://www.braintreegateway.com:443/merchants/yrvvxhf7w35y8v9f/transparent_redirect_requests" autocomplete="off" class="js-credit-card-form" id="create_braintree_customer" method="post">
				<div style="margin:0;padding:0;display:inline">
					<input name="authenticity_token" type="hidden" value="PV1gcNr48DRpmqGw8RQC/kvDFQ6jWLGkf3X1ob23uvo=">
				</div>

				<p class="js-thanks">
					Thanks for choosing to become a paying customer!  Once we successfully
					charge your credit card, we'll immediately upgrade your account to  the
					<strong class="js-new-plan-name">large</strong> plan.
					<input class="js-new-plan-name-val" id="customer_custom_fields_plan" name="customer[custom_fields][plan]" type="hidden" value="large">
				</p>
				<div class="rule"></div>

				<div class="form-cards cards_select">
					<input id="credit_card_type" name="credit_card_type" type="hidden" value="">

				</div>

				<dl class="form">
					<label for="customer_credit_card_number">Card Number</label>

					<input autocomplete="off" class="textfield" id="customer_credit_card_number" name="customer[credit_card][number]" required="required" size="30" type="text">
				</dl>
				<dl class="form">
					<label for="customer_credit_card_expiration_date">Expiration (MM/YYYY)</label>

					<input class="textfield short" id="customer_credit_card_expiration_date" name="customer[credit_card][expiration_date]" pattern="[\d]{1,2}/[\d]{4}" required="required" size="30" type="text">
				</dl>

				<dl class="form">
					<label for="customer_credit_card_cvv">CVV</label>

					<input class="textfield short" id="customer_credit_card_cvv" name="customer[credit_card][cvv]" pattern="[\d]{3,4}" required="required" size="30" type="text">
				</dl>

				<dl class="form">
					<label for="customer_credit_card_billing_address_postal_code">Postal Code</label>

					<input class="textfield short js-optional-postal-code" data-name="customer[credit_card][billing_address][postal_code]" id="customer_credit_card_billing_address_postal_code" name="" placeholder="Only required if you live in the U.S." size="30" type="text">
				</dl>

				<input id="tr_data" name="tr_data" type="hidden" value="4d5154afa8461f8157f2dcafbfbf6e3466b54075|api_version=2&amp;customer%5Bcustom_fields%5D%5Buser_id%5D=143610&amp;customer%5Bemail%5D=mhoegh%40gmail.com&amp;kind=create_customer&amp;public_key=hqytz65nx9kmmfk5&amp;redirect_url=github.com%2Faccount%2Fbilling%2Fbraintree_create&amp;time=20130304205624">

				<p class="legal">
					Please review the <a href="/site/terms" target="_blank">terms of service</a>,
					and <a href="/site/privacy" target="_blank">privacy policy</a>.  All sales are final
					— <strong>no refunds</strong>.
				</p>

				<div class="rule"></div>
			</form>
		</div>
	</div>
	<div class="modal-footer">
		<button class="btn" data-dismiss="modal" aria-hidden="true">
			Close
		</button>
		<button class="btn btn-primary">
			Process credit card
		</button>
	</div>
</div>
</body>
</html>