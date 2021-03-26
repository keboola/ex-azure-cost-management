#!/bin/bash
set -e

# OAuth version
export KBC_DEVELOPERPORTAL_APP=keboola.ex-azure-cost-management
./deploy.sh

# Service Principal version, same code, different UI
export KBC_DEVELOPERPORTAL_APP=keboola.ex-azure-cost-management-sp
./deploy.sh
