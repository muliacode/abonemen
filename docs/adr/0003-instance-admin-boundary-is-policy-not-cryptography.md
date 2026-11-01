# The Instance Admin boundary is policy, not cryptography

The Instance Admin never sees a Household's financial information unless they are a Member of it, but with server-side encryption the platform can technically decrypt that data, so only the application's rules enforce the boundary. We accepted this because the server must read data to send Reminders, convert currencies and compute statistics. Stronger separation needs zero-knowledge encryption, which is deferred until after 1.0. The threat model must say this plainly.
