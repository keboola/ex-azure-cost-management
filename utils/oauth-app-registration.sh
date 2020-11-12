#!/usr/bin/env bash

set -o errexit          # Exit on most errors (see the manual)
set -o errtrace         # Make sure any error trap is inherited
set -o nounset          # Disallow expansion of unset variables
set -o pipefail         # Use last non-zero exit code in a pipeline
#set -o xtrace          # Trace the execution of the script (debug)

# Load env variables from .env file, but not overwrite the existing one
if [ -f ".env" ]; then
  source <(grep -v '^#' .env | sed -E 's|^([^=]+)=(.*)$|: ${\1=\2}; export \1|g')
fi

# Required environment variables
: "${OAUTH_APP_NAME:?Need to set OAUTH_APP_NAME env variable}"

# Constants
SCRIPT=$(realpath "$0")
SCRIPT_DIR=$(dirname "$SCRIPT")
SCRIPT_FILENAME=$(basename "$SCRIPT")
AZ_CLI_IMG="mcr.microsoft.com/azure-cli"

# Permissions
# https://www.shawntabrizi.com/aad/common-microsoft-resources-azure-active-directory/
api_id="797f4846-ba00-4fd7-ba43-dac1f8f63013"
# https://github.com/stephaneey/azure-ad-vsts-extension/blob/master/overview.md
declare -A permissions
permissions["user_impersonation"]="41094075-9dad-400e-a0bd-54e686782033"

# If NOT run in the Docker container AND "az" executable not exists locally ...
if [ ! -f /.dockerenv ] && ! command -v az >/dev/null 2>&1; then
  # ... run script in Docker container
  echo "Running in Docker container ..."
  exec docker run \
    --rm -it \
    --volume "$SCRIPT_DIR:/utils" \
    -e OAUTH_APP_NAME \
    "$AZ_CLI_IMG" \
    "/utils/$SCRIPT_FILENAME"
fi

# Check if logged in, if not then login
subscriptionId=$(az account show --query "tenantId" --output tsv || true)
if [ -z "$subscriptionId" ]; then
  subscriptionId=$(az login --use-device-code --query "[].tenantId | [0]" --output tsv)
  echo "You have been successfully logged in!"
else
  echo "You are already logged in!"
fi

# Get app id if exists
echo "Testing if the application \"$OAUTH_APP_NAME\" exists ..."
OAUTH_APP_ID=$(az ad app list --output tsv --filter "displayName eq '$OAUTH_APP_NAME'" --query "[].appId | [0]")

# Create app if not exists
if [ -z "$OAUTH_APP_ID" ]; then
  echo "Application does not exist."
  echo "Creating application \"$OAUTH_APP_NAME\""
  OAUTH_APP_SECRET=`openssl rand -base64 32`
  OAUTH_APP_ID=$(
    az ad app create \
      --output tsv \
      --display-name "$OAUTH_APP_NAME" \
      --oauth2-allow-implicit-flow true \
      --available-to-other-tenants true \
      --end-date '2050-12-31' \
      --password "$OAUTH_APP_SECRET" \
      --query "appId"
  )
  echo "Application created, OAUTH_APP_ID=\"$OAUTH_APP_ID\""
  echo "SAVE SECRET KEY!!! -> OAUTH_APP_SECRET=\"$OAUTH_APP_SECRET\""
else
  echo "Application already exists, OAUTH_APP_ID=\"$OAUTH_APP_ID\""
fi

# Load active permissions
echo "Checking permission"
activePerms=$(az ad app list --output tsv --filter "displayName eq '$OAUTH_APP_NAME'"  --query "[].requiredResourceAccess[].resourceAccess[].id")

# Set permissions
perms_arg=()
for perm_name in "${!permissions[@]}"; do
  perm_id=${permissions[${perm_name}]}
  if [[ $activePerms != *"$perm_id"* ]]; then
    echo "Missing permission \"$perm_name\""
    perms_arg+=("$perm_id=Scope")
  fi
done

echo "Active permissions: $activePerms"

if [ ${#perms_arg[@]} -ne 0 ]; then
  echo "Setting permission"
  if ! az ad app permission add --id "$OAUTH_APP_ID" --api "$api_id" --api-permissions "${perms_arg[@]}" 2>/dev/null; then
    echo "WARNING: Error setting permissions."
    echo "WARNING: Please edit it manually in Azure Portal -> App registrations -> $OAUTH_APP_NAME -> Permissions"
  fi
fi

# Set public client = false
echo "Checking \"publicClient\" property"
publicClient=$(az ad app list --output tsv --filter "displayName eq '$OAUTH_APP_NAME'"  --query "[].publicClient | [0]")
if [ "$publicClient" != "false" ]; then
  echo "Setting publicClient=false"
  az ad app update --id "$OAUTH_APP_ID"  --set "publicClient=false" || true
fi

# Allow login with all types of account
echo "Checking \"signInAudience\" property"
signInAudience=$(az ad app list --output tsv --filter "displayName eq '$OAUTH_APP_NAME'"  --query "[].signInAudience | [0]")
if [ "$signInAudience" != "AzureADMultipleOrgs" ]; then
  echo "WARNING: Property \"signInAudience\" = \"$signInAudience\", but it should by set to \"AzureADMultipleOrgs\"."
  echo "WARNING: User won't be able to sign in with all types of accounts."
  echo "WARNING: Please edit it manually in Azure Portal -> App registrations -> $OAUTH_APP_NAME -> Manifest"
fi

# Print ENV variables
echo -e "\nDone\n"
echo -e "\n-----------------------------------------------------"
echo -e "Please, add these envrioment variables to \".env\" file:\n"
echo "OAUTH_APP_NAME=\"$OAUTH_APP_NAME\""
echo "OAUTH_APP_ID=$OAUTH_APP_ID"
echo "OAUTH_APP_SECRET=${OAUTH_APP_SECRET:-...}"
