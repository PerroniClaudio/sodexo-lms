import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import itLocale from '@fullcalendar/core/locales/it';

const calendarEl = document.getElementById('user-event-calendar');

if (calendarEl) {
  const calendar = new Calendar(calendarEl, {
    plugins: [dayGridPlugin],
    initialView: 'dayGridMonth',
    locale: itLocale,
    fixedWeekCount: false,
    showNonCurrentDates: false,
    headerToolbar: {
      start: '',
      center: '',
      end: 'prev title next',
    },
    buttonText: {
      prev: '',
      next: '',
    },
  });

  calendar.render();
}
