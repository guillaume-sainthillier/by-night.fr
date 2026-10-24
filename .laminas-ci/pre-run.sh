#!/bin/bash

set -ex

# This script runs before CI jobs
# PHPUnit does not run here but in the "PHPUnit (base image)" job of continuous-integration.yml,
# which sets up the test database itself

# Create var/tools directory for tools like Twig-CS-Fixer, PHPStan, etc.
mkdir -p var/tools

# Simplify permissions - set entire var directory accessible
chmod -R 777 var
