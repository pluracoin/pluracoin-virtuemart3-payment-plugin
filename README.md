# PluraCoin E-commerce Plugin for Virtuemart3/Joomla

v.1.0 beta

***This payment plugin is designed for Virtuemart 3 eshop owners who want to accept payments in PLURA (https://pluracoin.org) and become independent on classic payment options.***

How to install

1. Create folder plura in plugins/vmpayment and copy repository content there.
2. Log into your Joomla administration console and go to Extensions > Manage > Discover. Click on Discover button. The PLURA e-commerce plugin should appear in the list of found plugins.
3. Select the checkbox on the line with PLURA e-commerce plugin and click on Install button. The plugin will be installed.
4. Now go to Virtuemart > Configuration > Currencies and click the New button.
5. Add the new currency with following details and click on Save:
- Currency name: PLURA
- Published: Yes
- Code 2 letters: PA
- Code 3 letters: PLU
- Currency symbol: PLURA
- Decimals: 10
6. Go to Virtuemart > Shop > Payment Methods and click on the New button.
7. On the Payment Method Information fill in the form like this:
- Payment Name: PluraCoin
- Sef Alias: pluracoin
- Published: Yes
- Payment Description: Pay with PLURA cryptocurrency (see pluracoin.org)
- Payment Method: select "VM Payment - PluraCoin [PLURA]" from dropdown list
- Currency: PLURA
now switch to the Configuration tab and add the following info there
- Your wallet address: add here your PLURA wallet address where you want to receive payments from customers (if you don't have you wallet yet you can download a new one here https://pluracoin.org/#wallet)
- Your wallet view key: a hexadecimal key/string of your wallet used to check the status of payments in your wallet (safe! it's only view key)
- PLURA blockchain API address: leave as is unless you have your own node
- Number of confirmations required: leave as is or tweak to your needs (minimum = 1). Defines after how many confirmations is the payment valid = order is paid.

Done!

What is working:
- display payment method in the cart summary
- generate payment details
- display payment request on the last page of cart = ability to accept payments

TODO:
- cron script to periodically check for payments and update of order status
- list of received payments in Virtuemart admin console
- cron script for updating PLURA rate for major fiat currencies (USD, EUR, ...)
- dynamic calculation of PLURA price from base eshop currency based on current trading pairs rates (PLURA/USD, PLURA/EUR, ...)

