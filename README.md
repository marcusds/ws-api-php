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

// Optionally define functions to cache market data, if you want transactions' descriptions and accounts balances to show the security's symbol instead of its ID
// eg. sec-s-e7947deb977341ff9f0ddcf13703e9a6 => TSX:XEQT
$sec_info_getter_fn = function (string $ws_security_id) {
    if ($market_data = @file_get_contents(sys_get_temp_dir() . "/ws-api-$ws_security_id.json")) {
        return json_decode($market_data);
    }
    return NULL;
};
$sec_info_setter_fn = function (string $ws_security_id, object $market_data) {
    file_put_contents(sys_get_temp_dir() . "/ws-api-$ws_security_id.json", json_encode($market_data));
    return $market_data;
};
$ws->setSecurityMarketDataCache($sec_info_getter_fn, $sec_info_setter_fn);

// 4. Use the API object to access your WS accounts
$accounts = $ws->getAccounts();
foreach ($accounts as $account) {
    echo "Account: $account->description ($account->number)\n";
    if ($account->description === $account->unifiedAccountType) {
        // This is an "unknown" account, for which description is generic; please open an issue on https://github.com/gboudreau/ws-api-php/issues and include the following:
        echo "    Unknown account: " . json_encode($account) . "\n";
    }

    if ($account->currency === 'CAD') {
        $value = $account->financials->currentCombined->netLiquidationValue->amount;
        echo "  Net worth: $value $account->currency\n";
    }
    // Note: for USD accounts, $value is just the CAD value converted in USD, so it's not the real value of the account.
    // For USD accounts, only the balance & positions (below) are relevant.

    // Cash and positions balances
    $balances = $ws->getAccountBalances($account->id);
    $cash_balance = (float) $balances[$account->currency === 'USD' ? 'sec-c-usd' : 'sec-c-cad'] ?? 0;
    echo "  Available (cash) balance: $cash_balance $account->currency\n";
    if (count($balances) > 1) {
        echo "  Other positions:\n";
        foreach ($balances as $security => $bal) {
            if ($security === 'sec-c-cad' || $security === 'sec-c-usd') {
                continue;
            }
            echo "  - $security x $bal\n";
        }
    }

    $acts = $ws->getActivities($account->id);
    if ($acts) {
        echo "  Transactions:\n";
        // Activities are sorted by OCCURRED_AT_DESC by default; let's reverse
        $acts = array_reverse($acts);
    }
    foreach ($acts as $act) {
        if ($act->type === 'DIY_BUY') {
            $act->amountSign = 'negative';
        }
        echo "  - [" . date("Y-m-d H:i:s", strtotime($act->occurredAt)) . "] [$act->canonicalId] $act->description = " . ($act->amountSign === 'positive' ? "+" : "-") . "$act->amount $act->currency\n";
        if ($act->description === "$act->type: $act->subType") {
            // This is an "unknown" transaction, for which description is generic; please open an issue on https://github.com/gboudreau/ws-api-php/issues and include the following:
            echo "    Unknown activity: " . json_encode($act) . "\n";
        }
    }
    echo "\n";
}
```
