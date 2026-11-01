# Abonemen

A privacy-first tracker that lets households see what recurring services they pay for, when, and why, without touching a bank account.

## Language

### People and access

**Household**:
The scope that owns all data. A platform can have zero, one or many Households; each has at least one Member.
_Avoid_: Team, family, account, tenant

**Member**:
A person within a Household that Subscriptions can be held by. A Member may or may not be linked to a User.
_Avoid_: Person, profile, family member

**User**:
A sign-in identity. A User can belong to several Households, and belongs to a Household only through exactly one Member in it.
_Avoid_: Account, login

**Instance Admin**:
A User who operates the platform (platform configuration and administration), separate from any Household role. Never sees a Household's financial information unless they are a Member of that Household.
_Avoid_: Super admin, root

**Role**:
A named set of Permissions that a User holds in a Household. Owner, Editor, Contributor and Viewer are the default roles; Households may add custom roles after 1.0.
_Avoid_: Level, type

**Permission**:
A single ability within a Household, with a scope of all Subscriptions, only those the User holds, or only those visible to them. Example: cancel the Subscriptions you hold.

**Owner**:
The default role with full management rights over the Household and its data; it always carries every Permission and cannot be reduced. A Household always has at least one Owner, and may later display the role under a name of its choosing.
_Avoid_: Household admin, administrator

**Editor**:
The Household role that creates and edits all Subscriptions and other Household data, but does not manage the Household itself.

**Contributor**:
The Household role that creates and manages only the Subscriptions held by its own Member, and cannot reassign them.

**Viewer**:
The Household role that can only read.

**Invite**:
How a User joins a Household: an Owner invites one email address, choosing the role and the Member it links to (an unlinked one or a new one).

### Subscriptions

**Subscription**:
A recurring service, held by exactly one Member, paid on a cycle in a currency.
_Avoid_: Plan, contract, abonnement

**Holder**:
The Member in whose name a Subscription is held, and so the one responsible for managing or cancelling it.
_Avoid_: Subscriber, owner, account holder

**Visibility**:
Which Viewers and Contributors can see a Subscription: the Household's default (Visible to everyone, or Hidden from Viewers and Contributors), optionally overridden per Subscription. Roles with the Permission to see Restricted Subscriptions (Owner and Editor by default) see everything, and a Holder always sees what they hold.

**Subscription status**:
Where a Subscription is in its lifecycle: Trial, Active, Paused, Cancelled or Ended.

**Trial**:
A Subscription in a free period; when it ends it either converts to Active or ends, as chosen per Subscription. If it is cancelled after its cancel-by date, it becomes Cancelled at the trial's end, covering the first paid period.

**Paused**:
A Subscription temporarily stopped, producing no payments or costs, that returns to Active on a required resume date.

**Cancelled**:
A Subscription the User has cancelled whose paid period has not yet ended, until a required "ends on" date; it still counts as a cost until then.

**Ended**:
A Subscription that will never be charged again. Ended is final: coming back to the service means a new Subscription.

**Cancel-by date**:
The last day to cancel a Trial or a Subscription without being charged for the next period.

**Commitment**:
An optional minimum term on a Subscription, running to a commitment end date and optionally rolling over by a renewal length. Cancelling before it ends may cost an Early-cancellation fee.
_Avoid_: Contract, minimum term

**Early-cancellation fee**:
What cancelling inside a Commitment is expected to cost: nothing, a fixed amount, or a percentage of the payments remaining. Shown as information when cancelling and never added to totals.

**Price change**:
A new price for a Subscription that takes effect from a given date. Earlier payments keep the old price; Paid Payments are never rewritten.

**Category**:
The single group a Subscription belongs to, such as Streaming. A Household starts with a seeded, editable set.

**Tag**:
A free label; a Subscription can have any number.

**Payment method**:
A Household-level label typed by the user, such as "Visa ending 42", that a Subscription is paid with. It holds no real card or bank details.

### Payments and money

**Tracking mode**:
A per-Household choice of how payments are handled: Projected (payments are only calculated from the cycle) or Recorded (each payment is also stored and managed). Switching to Recorded applies from the switch date forward only.

**Payment**:
A single charge of a Subscription. In Recorded mode it is stored with a status of Due, Paid or Skipped, and an actual amount that defaults to the scheduled price; in Projected mode it is only calculated.

**Monthly equivalent**:
A Subscription's cost spread evenly per month, so a yearly 120 EUR plan counts as 10 EUR. It is the headline figure.

**Due this month**:
The cash view: the amount actually charged in the current month.

**Budget**:
A monthly amount in the Household's main currency for the whole Household, one Category, or one Member (by Holder). Household and Category Budgets are shown to Owners and Editors only; a Member's Budget is also shown to that Member's User.

### Reminders

**Reminder**:
A notice about an upcoming or overdue event: upcoming payment, cancel-by deadline, trial ending, resuming, or (Recorded mode only) an overdue Payment.

**Needs action**:
The dashboard list of Trials ending or cancel-by dates approaching, Paused Subscriptions about to resume, Cancelled Subscriptions about to end, overdue Due Payments (Recorded mode), and payment dates long past with nothing happening (Projected mode). Nothing else.

**Follow**:
A standing request by a User to receive Reminders for the whole Household, one Member, or one Subscription. Possible only for what that User can see.
