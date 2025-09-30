import React, { useEffect, useState, useMemo } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { championshipsAPI, racesAPI } from '../services/api';
import { Link } from 'react-router-dom';

// Helper to compute countdown parts
const useCountdown = (targetDate) => {
    const [now, setNow] = useState(Date.now());
    useEffect(() => {
        if (!targetDate) return;
        const id = setInterval(() => setNow(Date.now()), 1000);
        return () => clearInterval(id);
    }, [targetDate]);
    if (!targetDate) {
        return { d: '--', h: '--', m: '--', s: '--', finished: true };
    }
    const diff = Math.max(0, targetDate - now);
    const d = Math.floor(diff / (1000 * 60 * 60 * 24));
    const h = Math.floor((diff / (1000 * 60 * 60)) % 24);
    const m = Math.floor((diff / (1000 * 60)) % 60);
    const s = Math.floor((diff / 1000) % 60);
    return { d, h, m, s, finished: diff === 0 };
};

const StatusBadge = ({ status }) => {
    const map = {
        upcoming: 'bg-gray-700 text-gray-200',
        active: 'bg-primary text-white',
        completed: 'bg-green-600 text-white'
    };
    return <span className={`text-xs px-2 py-1 rounded font-semibold tracking-wide ${map[status] || 'bg-gray-700 text-gray-200'}`}>{status}</span>;
};

const ChampionshipCard = ({ champ, upcomingRace }) => {
    const target = upcomingRace ? new Date(upcomingRace.qualifying_date.replace(' ', 'T')).getTime() : null;
    const { d, h, m, s } = useCountdown(target);
    return (
        <div className="rounded-lg overflow-hidden shadow flex flex-col">
            <div className="bg-dark-100 text-white px-4 py-3 flex items-center justify-between">
                <div>
                    <h3 className="font-titillium font-bold text-lg leading-tight">{champ.name} <span className="text-primary">{champ.season_year}</span></h3>
                </div>
                <StatusBadge status={champ.status || champ.season_status} />
            </div>
            <div className="py-4 flex flex-col gap-4">
                <Link to={`/championships/${champ.id}`} className="flex items-center justify-around bg-primary/90 hover:bg-primary text-white rounded-md py-3 transition-colors">
                    <div className="text-center">
                        <div className="text-3xl font-bold font-titillium">{champ.user_position ?? '-'}</div>
                        <div className="text-xs uppercase tracking-wide">Posizione</div>
                    </div>
                    <div className="w-px h-10 bg-white/30" />
                    <div className="text-center">
                        <div className="text-3xl font-bold font-titillium">{champ.user_points != null ? Math.round(champ.user_points) : '0'}</div>
                        <div className="text-xs uppercase tracking-wide">Punti</div>
                    </div>
                </Link>
                {upcomingRace ? (
                    <div className="bg-black text-white rounded-md p-4 flex flex-col gap-2">
                        <div className="flex items-center justify-between text-xs text-gray-300">
                            <span>Prossima gara</span>
                            <span>{new Date(upcomingRace.qualifying_date.replace(' ', 'T')).toLocaleDateString()}</span>
                        </div>
                        <div className="text-center font-bold text-sm text-white uppercase leading-tight">{upcomingRace.name}</div>
                        <div className="mt-1 grid grid-cols-4 gap-2 text-center font-mono">
                            <div><div className="text-xl font-bold">{d}</div><div className="text-[10px] uppercase tracking-wide">D</div></div>
                            <div><div className="text-xl font-bold">{h.toString().padStart(2, '0')}</div><div className="text-[10px] uppercase tracking-wide">H</div></div>
                            <div><div className="text-xl font-bold">{m.toString().padStart(2, '0')}</div><div className="text-[10px] uppercase tracking-wide">M</div></div>
                            <div><div className="text-xl font-bold">{s.toString().padStart(2, '0')}</div><div className="text-[10px] uppercase tracking-wide">S</div></div>
                        </div>
                    </div>
                ) : (
                    <div className="bg-gray-100 rounded-md p-4 text-center text-sm text-gray-500">Nessuna gara imminente</div>
                )}
                <Link to={`/championships/${champ.id}/lineup/create`} className="mt-2 bg-primary text-white w-full text-center py-2 rounded-md font-semibold text-sm tracking-wide hover:bg-primary/90 transition-colors">
                    CREA LINEUP
                </Link>
            </div>
        </div>
    );
};

const Dashboard = () => {
    const { user } = useAuth();
    const [loading, setLoading] = useState(true);
    const [championships, setChampionships] = useState([]);
    const [races, setRaces] = useState([]);
    const [error, setError] = useState(null);

    useEffect(() => {
        const load = async () => {
            if (!user) return;
            try {
                setLoading(true);
                const [champsRes, racesRes] = await Promise.all([
                    championshipsAPI.getForUser(user.id),
                    racesAPI.getAll(2025)
                ]);
                const champs = champsRes.data?.data || champsRes.data || [];
                const raceList = racesRes.data?.races || racesRes.data || [];
                // Remove placeholder; backend now supplies user_points & user_position when user_id filter present
                const enriched = champs.map(c => ({ ...c }));
                setChampionships(enriched);
                setRaces(raceList);
            } catch (e) {
                setError(e.message || 'Failed to load dashboard');
            } finally {
                setLoading(false);
            }
        };
        load();
    }, [user]);

    const upcomingRacePerChamp = useMemo(() => {
        if (!races.length) return {};
        const now = Date.now();
        const next = races.filter(r => new Date(r.qualifying_date.replace(' ', 'T')).getTime() > now)
            .sort((a, b) => new Date(a.qualifying_date) - new Date(b.qualifying_date))[0];
        // same upcoming race for all championships for now (season wide)
        const map = {};
        championships.forEach(c => { map[c.id] = next; });
        return map;
    }, [races, championships]);

    return (
        <div className="min-h-screen bg-black">
            <main className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {error && <div className="bg-red-600/20 border border-red-500 text-red-200 px-4 py-3 rounded mb-6 text-sm">{error}</div>}
                {loading ? (
                    <div className="text-center text-gray-400 py-20">Loading dashboard...</div>
                ) : championships.length === 0 ? (
                    <div className="text-center text-gray-400 py-20">Non sei iscritto a nessun campionato.</div>
                ) : (
                    <div className="space-y-8">
                        {championships.map(ch => (
                            <ChampionshipCard key={ch.id} champ={ch} upcomingRace={upcomingRacePerChamp[ch.id]} />
                        ))}
                    </div>
                )}
            </main>
        </div>
    );
};

export default Dashboard;
