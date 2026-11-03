# What is encrypted, and who holds the keys

Protected: Subscription names, notes, URLs, prices and price history, Commitment and fee details, Member and Household names, User names and email addresses, Payment method labels, custom Category and Tag names, Budget amounts, logos, backups and notification secrets. Not protected, because the platform needs them to operate: roles, statuses, dates, cycles and currency codes. Emails are protected too, at the cost of the Instance Admin finding Users by exact address only. The Instance Admin is responsible for the master key and the recovery key; a Household cannot recover its own data. The "sensitive fields readable in a raw database dump" metric is measured against this list.

**Known limitation.** A protected value is bound to its Household's key but not to its row or column, so someone who can write to the database could swap two protected values within the same Household without it being detected. This is tracked in issue #49.
