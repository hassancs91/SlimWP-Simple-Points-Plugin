<?php

// Stripe PHP bindings
// https://github.com/stripe/stripe-php

if (!defined('ABSPATH')) {
    exit;
}

// Don't redefine the Stripe classes if they already exist
if (!class_exists('Stripe\Stripe')) {
    // Load essential utility classes first
    require_once __DIR__ . '/Util/ApiVersion.php';
    require_once __DIR__ . '/Util/CaseInsensitiveArray.php';
    require_once __DIR__ . '/Util/LoggerInterface.php';
    require_once __DIR__ . '/Util/DefaultLogger.php';
    require_once __DIR__ . '/Util/RandomGenerator.php';
    require_once __DIR__ . '/Util/RequestOptions.php';
    require_once __DIR__ . '/Util/Set.php';
    require_once __DIR__ . '/Util/Util.php';
    require_once __DIR__ . '/Util/ObjectTypes.php';
    require_once __DIR__ . '/Util/EventTypes.php';
    
    // Load exception classes
    require_once __DIR__ . '/Exception/ExceptionInterface.php';
    require_once __DIR__ . '/Exception/ApiErrorException.php';
    require_once __DIR__ . '/Exception/ApiConnectionException.php';
    require_once __DIR__ . '/Exception/AuthenticationException.php';
    require_once __DIR__ . '/Exception/BadMethodCallException.php';
    require_once __DIR__ . '/Exception/CardException.php';
    require_once __DIR__ . '/Exception/IdempotencyException.php';
    require_once __DIR__ . '/Exception/InvalidArgumentException.php';
    require_once __DIR__ . '/Exception/InvalidRequestException.php';
    require_once __DIR__ . '/Exception/PermissionException.php';
    require_once __DIR__ . '/Exception/RateLimitException.php';
    require_once __DIR__ . '/Exception/SignatureVerificationException.php';
    require_once __DIR__ . '/Exception/UnexpectedValueException.php';
    require_once __DIR__ . '/Exception/UnknownApiErrorException.php';
    require_once __DIR__ . '/Exception/TemporarySessionExpiredException.php';
    
    // Load HTTP client classes
    require_once __DIR__ . '/HttpClient/ClientInterface.php';
    require_once __DIR__ . '/HttpClient/StreamingClientInterface.php';
    require_once __DIR__ . '/HttpClient/CurlClient.php';
    
    // Load basic core classes
    require_once __DIR__ . '/RequestTelemetry.php';
    require_once __DIR__ . '/ApiResponse.php';
    require_once __DIR__ . '/ApiRequestor.php';
    require_once __DIR__ . '/StripeObject.php';
    
    // Load API operation traits BEFORE classes that use them
    require_once __DIR__ . '/ApiOperations/Request.php';
    require_once __DIR__ . '/ApiOperations/All.php';
    require_once __DIR__ . '/ApiOperations/Create.php';
    require_once __DIR__ . '/ApiOperations/Delete.php';
    require_once __DIR__ . '/ApiOperations/NestedResource.php';
    require_once __DIR__ . '/ApiOperations/Retrieve.php';
    require_once __DIR__ . '/ApiOperations/SingletonRetrieve.php';
    require_once __DIR__ . '/ApiOperations/Update.php';
    
    // Now load classes that use the traits
    require_once __DIR__ . '/ApiResource.php';
    require_once __DIR__ . '/SingletonApiResource.php';
    require_once __DIR__ . '/Collection.php';
    require_once __DIR__ . '/SearchResult.php';
    
    // Load client interfaces (base first, then extended)
    require_once __DIR__ . '/BaseStripeClientInterface.php';
    require_once __DIR__ . '/StripeClientInterface.php';
    require_once __DIR__ . '/StripeStreamingClientInterface.php';
    require_once __DIR__ . '/BaseStripeClient.php';
    require_once __DIR__ . '/StripeClient.php';
    
    // Load essential resource classes
    require_once __DIR__ . '/Account.php';
    require_once __DIR__ . '/AccountLink.php';
    require_once __DIR__ . '/AccountSession.php';
    require_once __DIR__ . '/ApplePayDomain.php';
    require_once __DIR__ . '/Application.php';
    require_once __DIR__ . '/ApplicationFee.php';
    require_once __DIR__ . '/ApplicationFeeRefund.php';
    require_once __DIR__ . '/Balance.php';
    require_once __DIR__ . '/BalanceTransaction.php';
    require_once __DIR__ . '/BankAccount.php';
    require_once __DIR__ . '/Capability.php';
    require_once __DIR__ . '/Card.php';
    require_once __DIR__ . '/CashBalance.php';
    require_once __DIR__ . '/Charge.php';
    require_once __DIR__ . '/ConfirmationToken.php';
    require_once __DIR__ . '/ConnectCollectionTransfer.php';
    require_once __DIR__ . '/CountrySpec.php';
    require_once __DIR__ . '/Coupon.php';
    require_once __DIR__ . '/CreditNote.php';
    require_once __DIR__ . '/CreditNoteLineItem.php';
    require_once __DIR__ . '/Customer.php';
    require_once __DIR__ . '/CustomerBalanceTransaction.php';
    require_once __DIR__ . '/CustomerCashBalanceTransaction.php';
    require_once __DIR__ . '/CustomerSession.php';
    require_once __DIR__ . '/Discount.php';
    require_once __DIR__ . '/Dispute.php';
    require_once __DIR__ . '/EphemeralKey.php';
    require_once __DIR__ . '/Event.php';
    require_once __DIR__ . '/ExchangeRate.php';
    require_once __DIR__ . '/File.php';
    require_once __DIR__ . '/FileLink.php';
    require_once __DIR__ . '/FundingInstructions.php';
    require_once __DIR__ . '/Invoice.php';
    require_once __DIR__ . '/InvoiceItem.php';
    require_once __DIR__ . '/InvoiceLineItem.php';
    require_once __DIR__ . '/InvoicePayment.php';
    require_once __DIR__ . '/InvoiceRenderingTemplate.php';
    require_once __DIR__ . '/LineItem.php';
    require_once __DIR__ . '/LoginLink.php';
    require_once __DIR__ . '/Mandate.php';
    require_once __DIR__ . '/PaymentIntent.php';
    require_once __DIR__ . '/PaymentLink.php';
    require_once __DIR__ . '/PaymentMethod.php';
    require_once __DIR__ . '/PaymentMethodConfiguration.php';
    require_once __DIR__ . '/PaymentMethodDomain.php';
    require_once __DIR__ . '/Payout.php';
    require_once __DIR__ . '/Person.php';
    require_once __DIR__ . '/Plan.php';
    require_once __DIR__ . '/Price.php';
    require_once __DIR__ . '/Product.php';
    require_once __DIR__ . '/ProductFeature.php';
    require_once __DIR__ . '/PromotionCode.php';
    require_once __DIR__ . '/Quote.php';
    require_once __DIR__ . '/Reason.php';
    require_once __DIR__ . '/RecipientTransfer.php';
    require_once __DIR__ . '/Refund.php';
    require_once __DIR__ . '/RelatedObject.php';
    require_once __DIR__ . '/ReserveTransaction.php';
    require_once __DIR__ . '/Review.php';
    require_once __DIR__ . '/SetupAttempt.php';
    require_once __DIR__ . '/SetupIntent.php';
    require_once __DIR__ . '/ShippingRate.php';
    require_once __DIR__ . '/Source.php';
    require_once __DIR__ . '/SourceMandateNotification.php';
    require_once __DIR__ . '/SourceTransaction.php';
    require_once __DIR__ . '/Subscription.php';
    require_once __DIR__ . '/SubscriptionItem.php';
    require_once __DIR__ . '/SubscriptionSchedule.php';
    require_once __DIR__ . '/TaxCode.php';
    require_once __DIR__ . '/TaxDeductedAtSource.php';
    require_once __DIR__ . '/TaxId.php';
    require_once __DIR__ . '/TaxRate.php';
    require_once __DIR__ . '/Token.php';
    require_once __DIR__ . '/Topup.php';
    require_once __DIR__ . '/Transfer.php';
    require_once __DIR__ . '/TransferReversal.php';
    require_once __DIR__ . '/Webhook.php';
    require_once __DIR__ . '/WebhookSignature.php';
    require_once __DIR__ . '/WebhookEndpoint.php';
    
    // Load Apps classes
    require_once __DIR__ . '/Apps/Secret.php';
    
    // Load Billing classes
    require_once __DIR__ . '/Billing/Alert.php';
    require_once __DIR__ . '/Billing/AlertTriggered.php';
    require_once __DIR__ . '/Billing/CreditBalanceSummary.php';
    require_once __DIR__ . '/Billing/CreditBalanceTransaction.php';
    require_once __DIR__ . '/Billing/CreditGrant.php';
    require_once __DIR__ . '/Billing/Meter.php';
    require_once __DIR__ . '/Billing/MeterEvent.php';
    require_once __DIR__ . '/Billing/MeterEventAdjustment.php';
    require_once __DIR__ . '/Billing/MeterEventSummary.php';
    
    // Load BillingPortal classes
    require_once __DIR__ . '/BillingPortal/Configuration.php';
    require_once __DIR__ . '/BillingPortal/Session.php';
    
    // Load Checkout classes
    require_once __DIR__ . '/Checkout/Session.php';
    
    // Load Climate classes
    require_once __DIR__ . '/Climate/Order.php';
    require_once __DIR__ . '/Climate/Product.php';
    require_once __DIR__ . '/Climate/Supplier.php';
    
    // Load Entitlements classes
    require_once __DIR__ . '/Entitlements/ActiveEntitlement.php';
    require_once __DIR__ . '/Entitlements/ActiveEntitlementSummary.php';
    require_once __DIR__ . '/Entitlements/Feature.php';
    
    // Load FinancialConnections classes
    require_once __DIR__ . '/FinancialConnections/Account.php';
    require_once __DIR__ . '/FinancialConnections/AccountOwner.php';
    require_once __DIR__ . '/FinancialConnections/AccountOwnership.php';
    require_once __DIR__ . '/FinancialConnections/Session.php';
    require_once __DIR__ . '/FinancialConnections/Transaction.php';
    
    // Load Forwarding classes
    require_once __DIR__ . '/Forwarding/Request.php';
    
    // Load Identity classes
    require_once __DIR__ . '/Identity/VerificationReport.php';
    require_once __DIR__ . '/Identity/VerificationSession.php';
    
    // Load Issuing classes
    require_once __DIR__ . '/Issuing/Authorization.php';
    require_once __DIR__ . '/Issuing/Card.php';
    require_once __DIR__ . '/Issuing/CardDetails.php';
    require_once __DIR__ . '/Issuing/Cardholder.php';
    require_once __DIR__ . '/Issuing/Dispute.php';
    require_once __DIR__ . '/Issuing/PersonalizationDesign.php';
    require_once __DIR__ . '/Issuing/PhysicalBundle.php';
    require_once __DIR__ . '/Issuing/Token.php';
    require_once __DIR__ . '/Issuing/Transaction.php';
    
    // Load Radar classes
    require_once __DIR__ . '/Radar/EarlyFraudWarning.php';
    require_once __DIR__ . '/Radar/ValueList.php';
    require_once __DIR__ . '/Radar/ValueListItem.php';
    
    // Load Reporting classes
    require_once __DIR__ . '/Reporting/ReportRun.php';
    require_once __DIR__ . '/Reporting/ReportType.php';
    
    // Load Sigma classes
    require_once __DIR__ . '/Sigma/ScheduledQueryRun.php';
    
    // Load Tax classes
    require_once __DIR__ . '/Tax/Calculation.php';
    require_once __DIR__ . '/Tax/CalculationLineItem.php';
    require_once __DIR__ . '/Tax/Registration.php';
    require_once __DIR__ . '/Tax/Settings.php';
    require_once __DIR__ . '/Tax/Transaction.php';
    require_once __DIR__ . '/Tax/TransactionLineItem.php';
    
    // Load Terminal classes
    require_once __DIR__ . '/Terminal/Configuration.php';
    require_once __DIR__ . '/Terminal/ConnectionToken.php';
    require_once __DIR__ . '/Terminal/Location.php';
    require_once __DIR__ . '/Terminal/Reader.php';
    
    // Load TestHelpers classes
    require_once __DIR__ . '/TestHelpers/TestClock.php';
    
    // Load Treasury classes
    require_once __DIR__ . '/Treasury/CreditReversal.php';
    require_once __DIR__ . '/Treasury/DebitReversal.php';
    require_once __DIR__ . '/Treasury/FinancialAccount.php';
    require_once __DIR__ . '/Treasury/FinancialAccountFeatures.php';
    require_once __DIR__ . '/Treasury/InboundTransfer.php';
    require_once __DIR__ . '/Treasury/OutboundPayment.php';
    require_once __DIR__ . '/Treasury/OutboundTransfer.php';
    require_once __DIR__ . '/Treasury/ReceivedCredit.php';
    require_once __DIR__ . '/Treasury/ReceivedDebit.php';
    require_once __DIR__ . '/Treasury/Transaction.php';
    require_once __DIR__ . '/Treasury/TransactionEntry.php';
    
    // Load V2 namespace classes
    require_once __DIR__ . '/V2/Billing/MeterEvent.php';
    require_once __DIR__ . '/V2/Billing/MeterEventAdjustment.php';
    require_once __DIR__ . '/V2/Billing/MeterEventSession.php';
    require_once __DIR__ . '/V2/Collection.php';
    require_once __DIR__ . '/V2/Event.php';
    require_once __DIR__ . '/V2/EventDestination.php';
    
    // Finally load the main Stripe class
    require_once __DIR__ . '/Stripe.php';
}