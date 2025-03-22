#!/bin/sh
until mysqladmin ping -h db -u bxubas_user -pbxubas_pass --silent; do
  echo "Waiting for MySQL to be ready..."
  sleep 1
done
echo "MySQL is ready!"
