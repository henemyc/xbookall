@php
    $firstDay = \Carbon\Carbon::create($year, $month, 1);
    $startDay = $firstDay->dayOfWeekIso;
    $emptyCells = $startDay - 1;
    $cellCount = 0;
@endphp

<tr>
    @for($i = 0; $i < $emptyCells; $i++)
        <td style="background: var(--bg);"></td>
        @php $cellCount++; @endphp
    @endfor

    @foreach($calendar as $day)
        @if($cellCount % 7 == 0 && $cellCount > 0)
            </tr><tr>
        @endif

        <td class="text-center" style="height: 72px; vertical-align: top; padding: 4px; {{ $day['is_today'] ? 'background: rgba(255, 107, 44, 0.06);' : '' }}">
            <a href="{{ $day['is_future'] ? 'javascript:void(0)' : route('panel.attendance.index', ['date' => $day['date']]) }}" 
               class="text-decoration-none d-block">
                <div class="fw-bold {{ $day['is_today'] ? 'text-white' : '' }}" 
                     style="{{ $day['is_today'] ? 'background: linear-gradient(135deg, #ff8a3d, #ff6b2c); width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: white; font-size: 13px;' : 'color: var(--text); font-size: 13px;' }} {{ $day['is_future'] ? 'opacity: 0.45;' : '' }}">
                    {{ $day['day'] }}
                </div>

                @if($day['present'] > 0)
                    <div class="mt-1" style="line-height: 1;">
                        <span style="font-size: 15px; font-weight: 700; color: {{ $day['is_today'] ? '#16c784' : '#16c784' }};">
                            {{ $day['present'] }}
                        </span>
                        <div style="font-size: 9.5px; color: #6b7280; margin-top: -2px;">members</div>
                    </div>
                @else
                    <div style="height: 22px;"></div>
                @endif
            </a>
        </td>
        @php $cellCount++; @endphp
    @endforeach

    @while($cellCount % 7 != 0)
        <td style="background: var(--bg);"></td>
        @php $cellCount++; @endphp
    @endwhile
</tr>