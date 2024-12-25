<?php

namespace PPFinances\Wealthsimple;

use PPFinances\Wealthsimple\Exceptions\WSApiException;

class WealthsimpleAPI extends WealthsimpleAPIBase
{
    protected const GRAPHQL_QUERIES = [
        'FetchAllAccountFinancials'     => "query FetchAllAccountFinancials(\$identityId: ID!, \$startDate: Date, \$pageSize: Int = 25, \$cursor: String) {\n  identity(id: \$identityId) {\n    id\n    ...AllAccountFinancials\n    __typename\n  }\n}\n\nfragment AllAccountFinancials on Identity {\n  accounts(filter: {}, first: \$pageSize, after: \$cursor) {\n    pageInfo {\n      hasNextPage\n      endCursor\n      __typename\n    }\n    edges {\n      cursor\n      node {\n        ...AccountWithFinancials\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment AccountWithFinancials on Account {\n  ...AccountWithLink\n  ...AccountFinancials\n  __typename\n}\n\nfragment AccountWithLink on Account {\n  ...Account\n  linkedAccount {\n    ...Account\n    __typename\n  }\n  __typename\n}\n\nfragment Account on Account {\n  ...AccountCore\n  custodianAccounts {\n    ...CustodianAccount\n    __typename\n  }\n  __typename\n}\n\nfragment AccountCore on Account {\n  id\n  archivedAt\n  branch\n  closedAt\n  createdAt\n  cacheExpiredAt\n  currency\n  requiredIdentityVerification\n  unifiedAccountType\n  supportedCurrencies\n  nickname\n  status\n  accountOwnerConfiguration\n  accountFeatures {\n    ...AccountFeature\n    __typename\n  }\n  accountOwners {\n    ...AccountOwner\n    __typename\n  }\n  type\n  __typename\n}\n\nfragment AccountFeature on AccountFeature {\n  name\n  enabled\n  __typename\n}\n\nfragment AccountOwner on AccountOwner {\n  accountId\n  identityId\n  accountNickname\n  clientCanonicalId\n  accountOpeningAgreementsSigned\n  name\n  email\n  ownershipType\n  activeInvitation {\n    ...AccountOwnerInvitation\n    __typename\n  }\n  sentInvitations {\n    ...AccountOwnerInvitation\n    __typename\n  }\n  __typename\n}\n\nfragment AccountOwnerInvitation on AccountOwnerInvitation {\n  id\n  createdAt\n  inviteeName\n  inviteeEmail\n  inviterName\n  inviterEmail\n  updatedAt\n  sentAt\n  status\n  __typename\n}\n\nfragment CustodianAccount on CustodianAccount {\n  id\n  branch\n  custodian\n  status\n  updatedAt\n  __typename\n}\n\nfragment AccountFinancials on Account {\n  id\n  custodianAccounts {\n    id\n    branch\n    financials {\n      current {\n        ...CustodianAccountCurrentFinancialValues\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  financials {\n    currentCombined {\n      id\n      ...AccountCurrentFinancials\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment CustodianAccountCurrentFinancialValues on CustodianAccountCurrentFinancialValues {\n  deposits {\n    ...Money\n    __typename\n  }\n  earnings {\n    ...Money\n    __typename\n  }\n  netDeposits {\n    ...Money\n    __typename\n  }\n  netLiquidationValue {\n    ...Money\n    __typename\n  }\n  withdrawals {\n    ...Money\n    __typename\n  }\n  __typename\n}\n\nfragment Money on Money {\n  amount\n  cents\n  currency\n  __typename\n}\n\nfragment AccountCurrentFinancials on AccountCurrentFinancials {\n  id\n  netLiquidationValue {\n    ...Money\n    __typename\n  }\n  netDeposits {\n    ...Money\n    __typename\n  }\n  simpleReturns(referenceDate: \$startDate) {\n    ...SimpleReturns\n    __typename\n  }\n  totalDeposits {\n    ...Money\n    __typename\n  }\n  totalWithdrawals {\n    ...Money\n    __typename\n  }\n  __typename\n}\n\nfragment SimpleReturns on SimpleReturns {\n  amount {\n    ...Money\n    __typename\n  }\n  asOf\n  rate\n  referenceDate\n  __typename\n}",
        'FetchActivityFeedItems'        => "query FetchActivityFeedItems(\$first: Int, \$cursor: Cursor, \$condition: ActivityCondition, \$orderBy: [ActivitiesOrderBy!] = OCCURRED_AT_DESC) {\n  activityFeedItems(\n    first: \$first\n    after: \$cursor\n    condition: \$condition\n    orderBy: \$orderBy\n  ) {\n    edges {\n      node {\n        ...Activity\n        __typename\n      }\n      __typename\n    }\n    pageInfo {\n      hasNextPage\n      endCursor\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment Activity on ActivityFeedItem {\n  accountId\n  aftOriginatorName\n  aftTransactionCategory\n  aftTransactionType\n  amount\n  amountSign\n  assetQuantity\n  assetSymbol\n  canonicalId\n  currency\n  eTransferEmail\n  eTransferName\n  externalCanonicalId\n  identityId\n  institutionName\n  occurredAt\n  p2pHandle\n  p2pMessage\n  spendMerchant\n  securityId\n  billPayCompanyName\n  billPayPayeeNickname\n  redactedExternalAccountNumber\n  opposingAccountId\n  status\n  subType\n  type\n  strikePrice\n  contractType\n  expiryDate\n  chequeNumber\n  provisionalCreditAmount\n  primaryBlocker\n  interestRate\n  frequency\n  counterAssetSymbol\n  rewardProgram\n  counterPartyCurrency\n  counterPartyCurrencyAmount\n  counterPartyName\n  fxRate\n  fees\n  reference\n  __typename\n}",
        'FetchSecuritySearchResult'     => "query FetchSecuritySearchResult(\$query: String!) {\n  securitySearch(input: {query: \$query}) {\n    results {\n      ...SecuritySearchResult\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment SecuritySearchResult on Security {\n  id\n  buyable\n  status\n  stock {\n    symbol\n    name\n    primaryExchange\n    __typename\n  }\n  securityGroups {\n    id\n    name\n    __typename\n  }\n  quoteV2 {\n    ... on EquityQuote {\n      marketStatus\n      __typename\n    }\n    __typename\n  }\n  __typename\n}",
        'FetchSecurityHistoricalQuotes' => "query FetchSecurityHistoricalQuotes(\$id: ID!, \$timerange: String! = \"1d\") {\n  security(id: \$id) {\n    id\n    historicalQuotes(timeRange: \$timerange) {\n      ...HistoricalQuote\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment HistoricalQuote on HistoricalQuote {\n  adjustedPrice\n  currency\n  date\n  securityId\n  time\n  __typename\n}",
        'FetchAccountsWithBalance'      => "query FetchAccountsWithBalance(\$ids: [String!]!, \$type: BalanceType!) {\n  accounts(ids: \$ids) {\n    ...AccountWithBalance\n    __typename\n  }\n}\n\nfragment AccountWithBalance on Account {\n  id\n  custodianAccounts {\n    id\n    financials {\n      ... on CustodianAccountFinancialsSo {\n        balance(type: \$type) {\n          ...Balance\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment Balance on Balance {\n  quantity\n  securityId\n  __typename\n}",
        'FetchSecurityMarketData'       => "query FetchSecurityMarketData(\$id: ID!) {\n  security(id: \$id) {\n    id\n    ...SecurityMarketData\n    __typename\n  }\n}\n\nfragment SecurityMarketData on Security {\n  id\n  allowedOrderSubtypes\n  marginRates {\n    ...MarginRates\n    __typename\n  }\n  fundamentals {\n    avgVolume\n    high52Week\n    low52Week\n    yield\n    peRatio\n    marketCap\n    currency\n    description\n    __typename\n  }\n  quote {\n    bid\n    ask\n    open\n    high\n    low\n    volume\n    askSize\n    bidSize\n    last\n    lastSize\n    quotedAsOf\n    quoteDate\n    amount\n    previousClose\n    __typename\n  }\n  stock {\n    primaryExchange\n    primaryMic\n    name\n    symbol\n    __typename\n  }\n  __typename\n}\n\nfragment MarginRates on MarginRates {\n  clientMarginRate\n  __typename\n}",
    ];

    public function getAccounts(bool $open_only = TRUE): array {
        return $this->doGraphQLQuery(
            'FetchAllAccountFinancials',
            [
                'pageSize' => 25,
                'identityId' => $this->getTokenInfo()->identity_canonical_id,
            ],
            'identity.accounts.edges',
            'array',
            $open_only ? fn($account) => $account->status === 'open' : NULL,
        );
    }

    public function getAccountBalances(string $account_id) : array {
        $accounts = $this->doGraphQLQuery(
            'FetchAccountsWithBalance',
            [
                'type' => 'TRADING',
                'ids'  => [$account_id],
            ],
            'accounts',
            'array',
        );

        $balances = [];
        foreach ($accounts[0]->custodianAccounts as $ca) {
            foreach ($ca->financials->balance as $bal) {
                $balances[$bal->securityId] = $bal->quantity;
            }
        }
        return $balances;
    }

    public function getActivities(string $account_id, int $how_many = 50, string $order_by = 'OCCURRED_AT_DESC', bool $ignore_rejected = TRUE): array {
        return $this->doGraphQLQuery(
            'FetchActivityFeedItems',
            [
                'orderBy' => $order_by,
                'first'   => $how_many,
                'condition' => [
                    'endDate'    => date('Y-m-d\TH:i:s.999\Z', strtotime('tomorrow')-1),
                    'accountIds' => [$account_id],
                ],
            ],
            'activityFeedItems.edges',
            'array',
            $ignore_rejected ? fn($act) => strpos($act->status ?? '', 'rejected') === FALSE : NULL,
        );
    }

    public function getSecurityMarketData(string $security_id): object {
        return $this->doGraphQLQuery(
            'FetchSecurityMarketData',
            ['id' => $security_id],
            'security',
            'object',
        );
    }

    public function searchSecurity(string $query): array {
        return $this->doGraphQLQuery(
            'FetchSecuritySearchResult',
            ['query' => $query],
            'securitySearch.results',
            'array',
        );
    }

    /**
     * Get historical quotes for a security.
     *
     * @param string $security_id Wealthsimple security ID, from searchSecurity() response
     * @param string $time_range  eg. 1m
     *
     * @return object[]
     * @throws WSApiException
     */
    public function getSecurityHistoricalQuotes(string $security_id, string $time_range = '1m'): array {
        return $this->doGraphQLQuery(
            'FetchSecurityHistoricalQuotes',
            [
                'id' => $security_id,
                'timerange' => $time_range,
            ],
            'security.historicalQuotes',
            'array',
        );
    }
}
