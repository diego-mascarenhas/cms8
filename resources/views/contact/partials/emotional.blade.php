<!-- Emotional History -->
                    <div class="card mb-4">
                        <h5 class="card-header d-flex justify-content-between align-items-center">
                            Emotional History
                            <button type="button" class="btn btn-primary btn-sm add-sentiment-btn">
                                + Añadir estado emocional
                            </button>
                        </h5>
                        <div class="card-body">
                            <ul class="timeline mb-4 ms-3">
                                @foreach ($data->sentimentHistories->sortByDesc('created_at')->take(5) as $sentimentHistory)
                                    <li class="timeline-item timeline-item-transparent">
                                        <span class="timeline-point timeline-point-transparent"
                                            style="background: none; font-size: 1.5em; display: flex; align-items: center; justify-content: center;">{!! $sentimentHistory->sentiment->emoji !!}</span>
                                        <div class="timeline-event">
                                            <div class="timeline-header mb-1">
                                                <h6 class="mb-0">{{ $sentimentHistory->notes }}</h6>
                                                <small class="text-muted">
                                                    @if ($sentimentHistory->created_at->diffInDays(now()) < 7)
                                                        {{ $sentimentHistory->created_at->diffForHumans() }}
                                                    @else
                                                        {{ $sentimentHistory->created_at->isoFormat('D [de] MMMM [de] YYYY, HH:mm [hs]') }}
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                                <!-- Added a base to prevent the timeline from ending abruptly -->
                                <li class="timeline-item timeline-item-transparent">
                                    <span class="timeline-point timeline-point-transparent"
                                        style="background: none; font-size: 1.5em; display: flex; align-items: center; justify-content: center;">•</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <!-- /Emotional History -->