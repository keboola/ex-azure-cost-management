# Azure Cost Management Extractor

[![Build Status](https://travis-ci.com/keboola/ex-azure-cost-management.svg?branch=master)](https://travis-ci.com/keboola/ex-azure-cost-management)

Exports data from the [Azure Cost Management APIs.](https://docs.microsoft.com/en-us/rest/api/cost-management).

# Usage

## Configuration

Extractor can be authenticated using [Keboola OAuth 2.0](https://developers.keboola.com/extend/generic-extractor/configuration/api/authentication/oauth20/)
or by `servicePrincipal` key in the configuration.

The configuration `config.json` contains following properties in `parameters` key: 
- `subscriptionId` - string (required): ID of the [Azure Subscription](https://techcommunity.microsoft.com/t5/azure/understanding-azure-account-subscription-and-directory/m-p/34800).
- `servicePrincipal` - optional (array), filled in only if "classic" OAuth authorization is not used
    - `tenant` - string (required): Tenant of the [Service Principal](https://docs.microsoft.com/en-us/azure/active-directory/develop/app-objects-and-service-principals).
    - `username` - string (required): Username of the [Service Principal](https://docs.microsoft.com/en-us/azure/active-directory/develop/app-objects-and-service-principals).
    - `#password` - string (required): Password of the [Service Principal](https://docs.microsoft.com/en-us/azure/active-directory/develop/app-objects-and-service-principals).
- `maxTries` - integer (optional): Number of the max tries if an error occurred. Default `5`.
- `export` - object (required): Configuration of the export.
    - `destination` - string (required): Name of the target table in the bucket.
    - `groupingDimensions` - enum[] (required):
        - An array that contains one or more `group by` columns.
        - These columns will be part of the output table.
        - Available values: `ServiceName`, `ResourceGroupName`, `ResourceLocation`, `Meter`, [read the whole list](https://github.com/keboola/ex-azure-cost-management/blob/master/src/ConfigDefinition.php#L40) ... 
    - `type` - enum (optional): One from: `ActualCost` - default, `AmortizedCost`, `Usage`.
    - `aggregation` - enum (optional): Aggregation's column, one from: `Cost` - default, `CostUSD`, `PreTaxCostUSD`, `UsageQuantity`, `PreTaxCost`.
    - `granularity` - enum (optional): One from: `None`, `Daily`, `Monthly`.
    - `incremental` - boolean (optional): Enables [Incremental Loading](https://help.keboola.com/storage/tables/#incremental-loading). Default `true`.
    - `timeDimension` - object (optional): Time dimensions of the export.
        - `timeFrame` - enum (optional): One from: `MonthToDate` - default, `WeekToDate`, `BillingMonthToDate`, `TheLastMonth`, `TheLastBillingMonth`, `Custom`.
        - `start` - string (optional): Start date of the `Custom` time frame in `YYYY-MM-DD` format.
        - `end` - string (optional): End date of the `Custom` time frame in `YYYY-MM-DD` format.

## OAuth

### Application 

OAuth app registration:
- If you are Keboola employee, you can use existing app `Keboola Azure Cost Extractor`. Credentials are stored in [1Password](https://1password.com).
- Or you can create a new app by `utils/oauth-app-registration.sh`
- Or you can create a new app manually in the `App registrations` section in the https://portal.azure.com.


Set `Redirect URIs`:
- Open `portal.azure.com` -> `App registrations` -> app-name -> `Authentication`
- In `Web` -> `Redirect URIs` click `Add URI`
- For development, you should add `http://localhost:10000/sign-in/callback`.
- Click `Save`


Please, store credentials in `.env` file.
```.env
OAUTH_APP_NAME="Keboola Azure Cost Extractor"
OAUTH_APP_ID=...
OAUTH_APP_SECRET=...
```

### Scopes

Set the required scopes in the Azure Portal in the settings of the OAuth application.

`API permissions` -> `Azure Service Management` -> `user_impersonation`

### Tokens

- OAuth tokens are result of login to the specific Azure account.
- OAuth login is not part of this repository. It is done by the [OAuth API](https://developers.keboola.com/extend/generic-extractor/configuration/api/authentication/oauth20/).
- Component uses the OAuth tokens to authorize to the [Azure Cost Management API](https://docs.microsoft.com/en-us/rest/api/cost-management).
- The `access_token` and `refresh_token` are part of `config.json` in `authorization.oauth_api.credentials.#data`.
- Component uses `refresh_token` (expires in 90 days) to generate new `access_token` (expires in 1 hour).
- For development / tests you must obtain this token manually:
    1. Setup environment variables `OAUTH_APP_NAME`, `OAUTH_APP_ID`, `OAUTH_APP_SECRET`
        - If are present in `.env` file, the script loads them.
    2. Run script `utils/oauth-login.sh`
    3. Follow the instructions (open the URL and login)
    4. Save tokens to `.env` file

## Development
 
Clone this repository and init the workspace with following command:

```
git clone https://github.com/keboola/ex-azure-cost-management
cd ex-azure-cost-management
docker-compose build
docker-compose run --rm dev composer install --no-scripts
```

Create `.env` file with following variables (from the previous steps)
```env
OAUTH_APP_NAME=
OAUTH_APP_ID=
OAUTH_APP_SECRET=
OAUTH_ACCESS_TOKEN=
OAUTH_REFRESH_TOKEN=
TEST_SUBSCRIPTION_ID=
```

Run the test suite using this command:

```
docker-compose run --rm dev composer tests
```
 
# Integration

For information about deployment and integration with KBC, please refer to the [deployment section of developers documentation](https://developers.keboola.com/extend/component/deployment/) 
