# Merchant Flow PHP application

Laravel 12 on Apiato 13 with Laravel Nova 5 and Spatie Permission. It owns the shared database schema and serves the admin portal at `/admin`. Setup, demo accounts and test commands are in the [main README](../README.md).

## Containers

| Container                   | Contents                                                                                            |
| --------------------------- | --------------------------------------------------------------------------------------------------- |
| `AppSection/User`           | User model, type flags and the observer that keeps Spatie roles in sync                             |
| `AppSection/Authorization`  | Spatie models, role and permission enums, the roles seeder, Nova policies for roles and permissions |
| `AppSection/Account`        | Merchant accounts and their user and agent links                                                    |
| `AppSection/Agent`          | Agents, their hierarchy and user links                                                              |
| `AppSection/Charge`         | Charge model with the capture, refund and void rules, actions that run them under a row lock        |
| `AppSection/Authentication` | Nova admin sign-in and the `api_tokens` table used by the Go API                                    |
| `AppSection/Demo`           | Local demo data seeder                                                                              |

Nova resources, actions, filters and metrics live in `app/Nova`. Every Nova ability, including attach and detach, is decided by the container policies.

Only users with the `admin` role can sign in to Nova. Agents and merchants use the merchant portal, which talks to the Go API only.
