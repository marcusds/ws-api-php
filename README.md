Unofficial Wealthsimple API Library for PHP
===========================================

This library allows you to access your own account using the Wealthsimple (GraphQL) API using PHP.

Installation
------------

```bash
composer require gboudreau/ws-api-php
```

Usage
-----

```php
<?php
require_once 'vendor/autoload.php';

use PPFinances\Wealthsimple\Exceptions\LoginFailedException;
use PPFinances\Wealthsimple\Exceptions\OTPRequiredException;
use PPFinances\Wealthsimple\Sessions\WSAPISession;
use PPFinances\Wealthsimple\WealthsimpleAPI;

// 1. Define a function that will be called when the session is created or updated. Persist the session to a safe place
$persist_session_fct = function (WSAPISession $session) {
    $json = json_encode($session);
    // @TODO Save $json somewhere safe; it contains tokens that can be used to empty your Wealthsimple account, so treat it with respect!
    // i.e. don't store it in a Git repository, or anywhere it can be accessed by others!
    // If you are running this on your own workstation, only you have access, and your drive is encrypted, it's OK to save it to a file:
    file_put_contents(__DIR__ . '/session.json', $json);
};

// 2. If it's the first time you run this, create a new session using the username & password (and TOTP answer, if needed). Do NOT save those infos in your code!
if (!file_exists(__DIR__ . '/session.json')) {
    $totp_code = null;
    while (true) {
        try {
            if (empty($username)) {
                $username = readline("Wealthsimple username (email): ");
            }
            if (empty($password)) {
                $password = readline("Password: ");
            }
            WealthsimpleAPI::login($username, $password, $totp_code, $persist_session_fct);
            // The above will throw exceptions if login failed
            // So we break (out of the login while(true) loop) on success:
            break;
        } catch (OTPRequiredException $e) {
            $totp_code = readline("TOTP code: ");
        } catch (LoginFailedException $e) {
            error_log("Login failed. Try again.");
            $username = null;
            $password = null;
        }
    }
}

// 3. Load the session object, and use it to instantiate the API object
$session = json_decode(file_get_contents(__DIR__ . '/session.json'));
$ws = WealthsimpleAPI::fromToken($session, $persist_session_fct);
// $persist_session_fct is needed here too, because the session may be updated if the access token expired, and thus this function will be called to save the new session

// 4. Use the API object to access your WS accounts
$accounts = $ws->getAccounts();
foreach ($accounts as $account) {
    $value = $account->financials->currentCombined->netLiquidationValue->amount;
    
    // $account->branch = 'TR' for Trade, 'WS' for Cash & managed accounts (i.e. accounts you can't trade)
    echo "Account: [$account->branch:$account->id] $account->nickname $account->type:$account->unifiedAccountType $account->currency\n";
    if ($account->currency === 'CAD') {
        echo "  Net worth: $value $account->currency\n";
    }
    // Note: for USD accounts, $value is just the CAD value converted in USD, so it's not the real value of the account.
    // For USD accounts, only the balance & positions (below) are relevant.

    // Cash and positions balances
    $balances = $ws->getAccountBalances($account->id);
    echo "  Available (cash) balance: " . ($balances[$account->currency === 'USD' ? 'sec-c-usd' : 'sec-c-cad'] ?? 0) . " $account->currency\n";
    if (count($balances) > 1) {
        echo "  Other positions:\n";
        foreach ($balances as $sec_id => $bal) {
            if ($sec_id === 'sec-c-cad' || $sec_id === 'sec-c-usd') {
                continue;
            }
            $stock = getWSStockInfo($ws, $sec_id);
            echo "  - {$stock->primaryExchange}:{$stock->symbol} x $bal\n";
        }
    }

    $acts = $ws->getActivities($account->id);
    if ($acts) {
        echo "  Transactions:\n";
        // Activities are sorted by OCCURRED_AT_DESC by default; let's reverse
        $acts = array_reverse($acts);
    }
    foreach ($acts as $act) {
        $what = '';
        if ($act->status === 'FILLED') {
            if ($act->type === 'DIY_SELL') {
                $stock = getWSStockInfo($ws, $act->securityId);
                $what = "= Sold " . ((float) $act->assetQuantity) . " x [$act->securityId] $stock->symbol @ " . ($act->amount / $act->assetQuantity);
            } elseif ($act->type === 'DIY_BUY') {
                $stock = getWSStockInfo($ws, $act->securityId);
                $what = "= Bought " . ((float) $act->assetQuantity) . " x [$act->securityId] $stock->symbol @ " . ($act->amount / $act->assetQuantity);
            }
        } elseif ($act->type === 'INSTITUTIONAL_TRANSFER_INTENT') {
            $what = 'Account transfer from another institution';
        } elseif ($act->subType === 'TRANSFER_FEE_REFUND') {
            $what = 'Refund of account transfer fees';
        } elseif ($account->branch === 'TR') {
            $what = 'Unknown transaction type';
        }
        echo "  - [" . date("Y-m-d H:i:s", strtotime($act->occurredAt)) . "] [$act->canonicalId] $act->type:$act->subType " . ($act->amountSign === 'positive' ? "+" : "-") . "$act->amount $act->currency $what\n";
        if ($what === 'Unknown transaction type') {
            echo "    " . json_encode($act) . "\n";
        }
    }
    echo "\n";
}

// This function is used to get a security (eg. stock) info, from a given security ID. This is useful to get a human-readable name for the security.
// eg. sec-s-e7947deb977341ff9f0ddcf13703e9a6 => XEQT
function getWSStockInfo($ws, $ws_security_id) {
    // Instead of querying the WS API every time you need to find the symbol of a security ID, you should cache the results in a local storage (eg. database)
    if ($market_data = @file_get_contents(sys_get_temp_dir() . "/ws-api-$ws_security_id.json")) {
        return json_decode($market_data)->stock;
    }
    $market_data = $ws->getSecurityMarketData($ws_security_id);
    file_put_contents(sys_get_temp_dir() . "/ws-api-$ws_security_id.json", json_encode($market_data));
    return $market_data->stock;
}
```
