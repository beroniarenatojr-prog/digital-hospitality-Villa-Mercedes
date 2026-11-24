const dayAfter = (day) => {
    const date = new Date(`${day}T12:00:00`);
    date.setDate(date.getDate() + 1);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};
const minutes = (time) => Number(time.slice(0, 2)) * 60 + Number(time.slice(3, 5));
const clock = (value) => `${String(Math.floor(value / 60)).padStart(2, '0')}:${String(value % 60).padStart(2, '0')}`;

/** Shared date controls for browsing, reservation creation, and rescheduling. */
export function reservationSchedule(policy, initial = {}) {
    return {
        policy,
        visitDate: String(initial.check_in || policy.defaultDate).slice(0, 10),
        departureDate: String(initial.check_out || '').slice(0, 10),
        startTime: String(initial.check_in || '').slice(11, 16) || policy.hallOpen,
        slot: String(initial.check_in || '').slice(11, 16) === policy.slots.night.start ? 'night' : 'day',
        sameDay: policy.mode === 'room' && !!initial.check_in && String(initial.check_in).slice(0, 10) === String(initial.check_out).slice(0, 10),
        roomEndTime: String(initial.check_out || '').slice(11, 16) || '17:00',
        earlyHours: policy.mode === 'room' ? Math.min(policy.maxEarlyHours, Math.max(0, Number(initial.early_check_in_hours || 0))) : 0,
        extensionHours: 0,
        scheduleMessage: '',

        init() {
            if (policy.mode === 'hall' && initial.check_in && initial.check_out) {
                const duration = (new Date(initial.check_out.replace(' ', 'T')) - new Date(initial.check_in.replace(' ', 'T'))) / 3600000;
                this.extensionHours = Math.max(0, Math.floor(duration - policy.standardHours));
            }
            this.sync(true);
            const original = String(initial.check_in || '').replace('T', ' ').slice(0, 16);
            const originalEnd = String(initial.check_out || '').replace('T', ' ').slice(0, 16);
            if (original && (original !== this.selection.check_in || originalEnd !== this.selection.check_out || Number(initial.late_check_out_hours || 0) > 0 || Number(initial.early_check_in_hours || 0) !== this.earlyHours)) {
                this.scheduleMessage = 'Your saved visit has been adjusted to the current schedule. Review the dates and hours before continuing.';
                this.$dispatch('schedule-adjusted');
            }
        },
        get overnight() { return policy.overnight && !this.sameDay; },
        get latestStart() { return clock(minutes(policy.hallClose) - policy.standardHours * 60); },
        get maxExtension() {
            if (!policy.extensionHourCents) return 0;
            return Math.max(0, Math.floor((minutes(policy.hallClose) - minutes(this.startTime)) / 60) - policy.standardHours);
        },
        get selection() {
            let start = policy.start;
            let end = policy.end;
            let endDay = this.overnight ? this.departureDate : this.visitDate;
            if (policy.mode === 'room' && this.sameDay) {
                start = this.startTime;
                end = this.roomEndTime;
            } else if (policy.mode === 'opal') {
                ({start, end} = policy.slots[this.slot]);
            } else if (policy.mode === 'hall') {
                start = this.startTime;
                end = clock(minutes(start) + (policy.standardHours + Number(this.extensionHours)) * 60);
            }
            return {
                check_in: this.visitDate ? `${this.visitDate} ${start}` : '',
                check_out: endDay ? `${endDay} ${end}` : '',
                early_check_in_hours: this.sameDay ? 0 : Number(this.earlyHours),
                late_check_out_hours: 0,
                hall_extension_hours: Number(this.extensionHours),
            };
        },
        timeLabel(time) {
            const hours = Number(time.slice(0, 2));
            return `${hours % 12 || 12}:${time.slice(3, 5)} ${hours < 12 ? 'AM' : 'PM'}`;
        },
        get endLabel() { return this.timeLabel(this.selection.check_out.slice(11, 16)); },
        sync(initialChange = false) {
            if (this.overnight && (!this.departureDate || this.departureDate <= this.visitDate)) this.departureDate = dayAfter(this.visitDate);
            if (policy.mode === 'hall') {
                if (this.startTime < policy.hallOpen || this.startTime > this.latestStart) this.startTime = policy.hallOpen;
                this.extensionHours = Math.min(this.maxExtension, Math.max(0, Math.floor(Number(this.extensionHours) || 0)));
            }
            this.$dispatch('schedule-changed', {...this.selection, initial: initialChange});
        },
    };
}
