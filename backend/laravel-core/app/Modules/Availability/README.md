# Availability module

Availability owns staff eligibility, slot calculation, conflict checks, and temporary slot coordination.

Current implementation status: availability behavior remains inside the relocated Booking `BookingFlowService` compatibility facade. It will be extracted one tested use case at a time; MySQL transaction and conflict checks remain authoritative.
