# Payment deployment and verification

Deploy the application code and compiled assets together. Take a database backup and run `php artisan migrate --force` using your migration account. The migration adds a delivery instructions field to orders and adds transfer fields to the existing payments table. Existing payment rows default to Paystack and keep their references, amounts and statuses. Orders remain the source of truth for currency, line-item prices and the amount due.

## Paystack

Keep `PAYSTACK_SECRET_KEY` in the server environment. Use the restaurant’s verified Paystack account, enable the desired channels in the Paystack Dashboard and start with its test key. `PAYSTACK_PUBLIC_KEY` is available for configuration but the hosted redirect uses the secret server-side. Keep `PAYSTACK_PAYMENT_URL=https://api.paystack.co`. Leave the secret blank to disable online checkout.

Configure `https://YOUR-PUBLIC-HOST/payments/paystack/webhook` in the same Paystack mode as the key. For the legacy `x-paystack-signature` header the application uses the API secret. If Paystack supplies timestamped webhook secrets, put the current and any overlapping rotation secrets in the comma-separated `PAYSTACK_WEBHOOK_SECRET` setting. When dedicated secrets are configured, unsigned or legacy-only requests are rejected. Clear and rebuild configuration after changes with `php artisan config:cache`. Run Laravel’s scheduler at least once per minute so the existing Paystack reconciliation command can run. It only polls Paystack orders.

On a staging site use [Paystack test payments](https://paystack.com/docs/payments/test-payments/) to run a successful transaction and abandoned and failed scenarios. Review the webhook delivery in the Paystack Dashboard. Confirm that only the verified successful transaction changes the payment to Paid and that replay does not duplicate order progress or email. Use the Laravel test suite for mismatch and duplicate tests. Test mode cannot settle a real payment.

## Bank transfer

On a fresh installation, add the receiving account and instructions in **Admin → Payments → Bank transfer settings**, then enable bank transfer. Enter the restaurant's contact email in **Website settings**. Saving new details only affects new orders. Existing orders retain the bank details shown when they were placed. The checkout continues to use the account details on the order, even when new transfers are disabled.

Select Bank Transfer from a checkout using a confirmed menu price. The order page should display the exact amount and order reference. Use **I Have Made Payment** to submit the report. The order should remain Pending Payment. Staff can find it through **Admin → Payments → Transfers awaiting review → Review transfer**.

Check the actual bank statement first. Match the order’s exact amount and receiving account, then enter the bank’s statement transaction reference and confirm receipt. The statement reference cannot be used for another order. If receipt cannot be verified, choose **Reject Bank Transfer** and provide a customer-facing reason. A rejected order stays unpaid. Refunds and mismatched or late bank transfers require manual reconciliation.

## Before enabling production ordering

Confirm menu prices and delivery fees in the existing admin controls. Verify the bank account with the restaurant owner, activate the approved Paystack account and webhook with real credentials, and complete the staging checklist before taking live payments. For optional customer notices configure the existing SMTP and queue worker and set `RESTAURANT_SEND_MAIL=true`. Checkout and transfer review also work with mail disabled. Payment actions are visible in the customer order history and in admin.
